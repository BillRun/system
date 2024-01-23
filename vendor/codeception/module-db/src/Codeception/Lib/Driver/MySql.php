<?php

declare(strict_types=1);

namespace Codeception\Lib\Driver;

use PDO;

class MySql extends Db
{
    public function cleanup(): void
    {
        $this->dbh->exec('SET FOREIGN_KEY_CHECKS=0;');
        $res = $this->dbh->query("SHOW FULL TABLES WHERE TABLE_TYPE LIKE '%TABLE';")->fetchAll();
        foreach ($res as $row) {
            $this->dbh->exec('drop table `' . $row[0] . '`');
        }
        $this->dbh->exec('SET FOREIGN_KEY_CHECKS=1;');
    }

    protected function sqlQuery(string $query): void
    {
        $this->dbh->exec('SET FOREIGN_KEY_CHECKS=0;');
        parent::sqlQuery($query);
        $this->dbh->exec('SET FOREIGN_KEY_CHECKS=1;');
    }

    public function getQuotedName(string $name): string
    {
        return '`' . str_replace('.', '`.`', $name) . '`';
    }

    /**
     * @return string[]
     */
    public function getPrimaryKey(string $tableName): array
    {
        if (!isset($this->primaryKeys[$tableName])) {
            $primaryKey = [];
            $stmt = $this->getDbh()->query(
                'SHOW KEYS FROM ' . $this->getQuotedName($tableName) . " WHERE Key_name = 'PRIMARY'"
            );
            $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($columns as $column) {
                $primaryKey []= $column['Column_name'];
            }
            $this->primaryKeys[$tableName] = $primaryKey;
        }

        return $this->primaryKeys[$tableName];
    }
}
