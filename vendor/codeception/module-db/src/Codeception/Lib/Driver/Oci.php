<?php

declare(strict_types=1);

namespace Codeception\Lib\Driver;

class Oci extends Db
{
    public function setWaitLock(int $seconds): void
    {
        $this->dbh->exec('ALTER SESSION SET ddl_lock_timeout = ' . $seconds);
    }

    public function cleanup(): void
    {
        $this->dbh->exec(
            "BEGIN
                        FOR i IN (SELECT trigger_name FROM user_triggers)
                          LOOP
                            EXECUTE IMMEDIATE('DROP TRIGGER ' || user || '.\"' || i.trigger_name || '\"');
                          END LOOP;
                      END;"
        );
        $this->dbh->exec(
            "BEGIN
                        FOR i IN (SELECT table_name FROM user_tables)
                          LOOP
                            EXECUTE IMMEDIATE('DROP TABLE ' || user || '.\"' || i.table_name || '\" CASCADE CONSTRAINTS');
                          END LOOP;
                      END;"
        );
        $this->dbh->exec(
            "BEGIN
                        FOR i IN (SELECT sequence_name FROM user_sequences)
                          LOOP
                            EXECUTE IMMEDIATE('DROP SEQUENCE ' || user || '.\"' || i.sequence_name || '\"');
                          END LOOP;
                      END;"
        );
        $this->dbh->exec(
            "BEGIN
                        FOR i IN (SELECT view_name FROM user_views)
                          LOOP
                            EXECUTE IMMEDIATE('DROP VIEW ' || user || '.\"' || i.view_name || '\"');
                          END LOOP;
                      END;"
        );
    }

    /**
     * SQL commands should ends with `//` in the dump file
     * IF you want to load triggers too.
     * IF you do not want to load triggers you can use the `;` characters
     * but in this case you need to change the $delimiter from `//` to `;`
     *
     * @param string[] $sql
     */
    public function load(array $sql): void
    {
        $query = '';
        $delimiter = '//';
        $delimiterLength = 2;

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
                $query = "";
            }
        }

        if ($query !== '') {
            $this->sqlQuery($query);
        }
    }

    /**
     * @return string[]
     */
    public function getPrimaryKey(string $tableName): array
    {
        if (!isset($this->primaryKeys[$tableName])) {
            $primaryKey = [];
            $query = "SELECT cols.column_name
                FROM all_constraints cons, all_cons_columns cols
                WHERE cols.table_name = ?
                AND cons.constraint_type = 'P'
                AND cons.constraint_name = cols.constraint_name
                AND cons.owner = cols.owner
                ORDER BY cols.table_name, cols.position";
            $stmt = $this->executeQuery($query, [$tableName]);
            $columns = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            foreach ($columns as $column) {
                $primaryKey []= $column['COLUMN_NAME'];
            }

            $this->primaryKeys[$tableName] = $primaryKey;
        }

        return $this->primaryKeys[$tableName];
    }
}
