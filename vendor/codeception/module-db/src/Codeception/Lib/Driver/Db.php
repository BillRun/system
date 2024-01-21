<?php

declare(strict_types=1);

namespace Codeception\Lib\Driver;

use Codeception\Exception\ModuleException;
use Exception;
use InvalidArgumentException;
use PDO;
use PDOException;
use PDOStatement;

class Db
{
    protected ?PDO $dbh = null;

    protected string $dsn;

    protected string $user;

    protected string $password;

    /**
     * @see https://www.php.net/manual/de/pdo.construct.php
     */
    protected ?array $options = [];

    /**
     * Associative array with table name => primary-key
     */
    protected array $primaryKeys = [];

    public static function connect(string $dsn, string $user = null, string $password = null, array $options = null): PDO
    {
        $dbh = new PDO($dsn, $user, $password, $options);
        $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        return $dbh;
    }

    /**
     * @static
     *
     * @see https://www.php.net/manual/en/pdo.construct.php
     * @see https://www.php.net/manual/de/ref.pdo-mysql.php#pdo-mysql.constants
     *
     * @return Db|SqlSrv|MySql|Oci|PostgreSql|Sqlite
     */
    public static function create(string $dsn, string $user = null, string $password = null, array $options = null): Db
    {
        $provider = self::getProvider($dsn);

        switch ($provider) {
            case 'sqlite':
                return new Sqlite($dsn, $user, $password, $options);
            case 'mysql':
                return new MySql($dsn, $user, $password, $options);
            case 'pgsql':
                return new PostgreSql($dsn, $user, $password, $options);
            case 'mssql':
            case 'dblib':
            case 'sqlsrv':
                return new SqlSrv($dsn, $user, $password, $options);
            case 'oci':
                return new Oci($dsn, $user, $password, $options);
            default:
                return new Db($dsn, $user, $password, $options);
        }
    }

    public static function getProvider($dsn): string
    {
        return substr($dsn, 0, strpos($dsn, ':'));
    }

    /**
     * @see https://www.php.net/manual/en/pdo.construct.php
     * @see https://www.php.net/manual/de/ref.pdo-mysql.php#pdo-mysql.constants
     */
    public function __construct(string $dsn, string $user = null, string $password = null, array $options = null)
    {
        $this->dbh = new PDO($dsn, $user, $password, $options);
        $this->dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->dsn = $dsn;
        $this->user = $user;
        $this->password = $password;
        $this->options = $options;
    }

    public function __destruct()
    {
        if ($this->dbh !== null && $this->dbh->inTransaction()) {
            $this->dbh->rollBack();
        }

        $this->dbh = null;
    }

    public function getDbh(): PDO
    {
        return $this->dbh;
    }

    public function getDb()
    {
        $matches = [];
        $matched = preg_match('#dbname=(\w+)#s', $this->dsn, $matches);
        if (!$matched) {
            return false;
        }

        return $matches[1];
    }

    public function cleanup(): void
    {
    }

    /**
     * Set the lock waiting interval for the database session
     */
    public function setWaitLock(int $seconds): void
    {
    }

    /**
     * @param string[] $sql
     */
    public function load(array $sql): void
    {
        $query = '';
        $delimiter = ';';
        $delimiterLength = 1;

        foreach ($sql as $singleSql) {
            if (preg_match('#DELIMITER ([\;\$\|\\\]+)#i', $singleSql, $match)) {
                $delimiter = $match[1];
                $delimiterLength = strlen($delimiter);
                continue;
            }

            $parsed = $this->sqlLine($singleSql);
            if ($parsed) {
                continue;
            }

            $query .= "\n" . rtrim($singleSql);

            if (substr($query, -1 * $delimiterLength, $delimiterLength) == $delimiter) {
                $this->sqlQuery(substr($query, 0, -1 * $delimiterLength));
                $query = '';
            }
        }

        if ($query !== '') {
            $this->sqlQuery($query);
        }
    }

    public function insert(string $tableName, array &$data): string
    {
        $columns = array_map(
            fn($name): string => $this->getQuotedName($name),
            array_keys($data)
        );

        return sprintf(
            "INSERT INTO %s (%s) VALUES (%s)",
            $this->getQuotedName($tableName),
            implode(', ', $columns),
            implode(', ', array_fill(0, count($data), '?'))
        );
    }

    public function select(string $column, string $tableName, array &$criteria): string
    {
        $where = $this->generateWhereClause($criteria);

        $query = "SELECT %s FROM %s %s";
        return sprintf($query, $column, $this->getQuotedName($tableName), $where);
    }

    /**
     * @return string[]
     */
    private function getSupportedOperators(): array
    {
        return [
            'like',
            '!=',
            '<=',
            '>=',
            '<',
            '>',
        ];
    }

    protected function generateWhereClause(array &$criteria): string
    {
        if (empty($criteria)) {
            return '';
        }

        $operands = $this->getSupportedOperators();

        $params = [];
        foreach ($criteria as $k => $v) {
            if ($v === null) {
                if (strpos($k, ' !=') > 0) {
                    $params[] = $this->getQuotedName(str_replace(" !=", '', $k)) . " IS NOT NULL ";
                } else {
                    $params[] = $this->getQuotedName($k) . " IS NULL ";
                }

                unset($criteria[$k]);
                continue;
            }

            $hasOperand = false; // search for equals - no additional operand given

            foreach ($operands as $operand) {
                if (!stripos($k, " {$operand}") > 0) {
                    continue;
                }

                $hasOperand = true;
                $k = str_ireplace(" {$operand}", '', $k);
                $operand = strtoupper($operand);
                $params[] = $this->getQuotedName($k) . " {$operand} ? ";
                break;
            }

            if (!$hasOperand) {
                $params[] = $this->getQuotedName($k) . " = ? ";
            }
        }

        return 'WHERE ' . implode('AND ', $params);
    }

    public function deleteQueryByCriteria(string $tableName, array $criteria): void
    {
        $where = $this->generateWhereClause($criteria);

        $query = 'DELETE FROM ' . $this->getQuotedName($tableName) . ' ' . $where;
        $this->executeQuery($query, array_values($criteria));
    }

    public function lastInsertId(string $tableName): string
    {
        return $this->getDbh()->lastInsertId();
    }

    public function getQuotedName(string $name): string
    {
        return '"' . str_replace('.', '"."', $name) . '"';
    }

    protected function sqlLine(string $sql): bool
    {
        $sql = trim($sql);
        return (
            $sql === ''
            || $sql === ';'
            || preg_match('#^((--.*?)|(\#))#s', $sql)
        );
    }

    protected function sqlQuery(string $query): void
    {
        try {
            $this->dbh->exec($query);
        } catch (PDOException $exception) {
            throw new ModuleException(
                \Codeception\Module\Db::class,
                $exception->getMessage() . "\nSQL query being executed: " . $query
            );
        }
    }

    public function executeQuery($query, array $params): PDOStatement
    {
        $pdoStatement = $this->dbh->prepare($query);
        if (!$pdoStatement) {
            throw new Exception("Query '{$query}' can't be prepared.");
        }

        $i = 0;
        foreach ($params as $param) {
            ++$i;
            if (is_bool($param)) {
                $type = PDO::PARAM_BOOL;
            } elseif (is_int($param)) {
                $type = PDO::PARAM_INT;
            } else {
                $type = PDO::PARAM_STR;
            }

            $pdoStatement->bindValue($i, $param, $type);
        }

        $pdoStatement->execute();
        return $pdoStatement;
    }

    /**
     * @return string[]
     */
    public function getPrimaryKey(string $tableName): array
    {
        return [];
    }

    protected function flushPrimaryColumnCache(): bool
    {
        $this->primaryKeys = [];

        return empty($this->primaryKeys);
    }

    public function update(string $tableName, array $data, array $criteria): string
    {
        if (empty($data)) {
            throw new InvalidArgumentException(
                "Query update can't be prepared without data."
            );
        }

        $set = [];
        foreach (array_keys($data) as $column) {
            $set[] = $this->getQuotedName($column) . " = ?";
        }

        $where = $this->generateWhereClause($criteria);

        return sprintf('UPDATE %s SET %s %s', $this->getQuotedName($tableName), implode(', ', $set), $where);
    }

    public function getOptions(): array
    {
        return $this->options;
    }
}
