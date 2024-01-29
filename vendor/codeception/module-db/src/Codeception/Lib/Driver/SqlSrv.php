<?php

declare(strict_types=1);

namespace Codeception\Lib\Driver;

use PDO;

class SqlSrv extends Db
{
    public function getDb()
    {
        $matches = [];
        $matched = preg_match('#Database=(.*);?#s', $this->dsn, $matches);

        if (!$matched) {
            return false;
        }

        return $matches[1];
    }

    public function cleanup(): void
    {
        $this->dbh->exec(
            "
            DECLARE constraints_cursor CURSOR FOR SELECT name, parent_object_id FROM sys.foreign_keys;
            OPEN constraints_cursor
            DECLARE @constraint sysname;
            DECLARE @parent int;
            DECLARE @table nvarchar(128);
            FETCH NEXT FROM constraints_cursor INTO @constraint, @parent;
            WHILE (@@FETCH_STATUS <> -1)
            BEGIN
                SET @table = OBJECT_NAME(@parent)
                EXEC ('ALTER TABLE [' + @table + '] DROP CONSTRAINT [' + @constraint + ']')
                FETCH NEXT FROM constraints_cursor INTO @constraint, @parent;
            END
            DEALLOCATE constraints_cursor;"
        );

        $this->dbh->exec(
            "
            DECLARE tables_cursor CURSOR FOR SELECT name FROM sysobjects WHERE type = 'U';
            OPEN tables_cursor DECLARE @tablename sysname;
            FETCH NEXT FROM tables_cursor INTO @tablename;
            WHILE (@@FETCH_STATUS <> -1)
            BEGIN
                EXEC ('DROP TABLE [' + @tablename + ']')
                FETCH NEXT FROM tables_cursor INTO @tablename;
            END
            DEALLOCATE tables_cursor;"
        );
    }

    public function getQuotedName(string $name): string
    {
        return '[' . str_replace('.', '].[', $name) . ']';
    }

    /**
     * @return string[]
     */
    public function getPrimaryKey(string $tableName): array
    {
        if (!isset($this->primaryKeys[$tableName])) {
            $primaryKey = [];
            $query = "
                SELECT Col.Column_Name from
                    INFORMATION_SCHEMA.TABLE_CONSTRAINTS Tab,
                    INFORMATION_SCHEMA.CONSTRAINT_COLUMN_USAGE Col
                WHERE
                    Col.Constraint_Name = Tab.Constraint_Name
                    AND Col.Table_Name = Tab.Table_Name
                    AND Constraint_Type = 'PRIMARY KEY' AND Col.Table_Name = ?";
            $stmt = $this->executeQuery($query, [$tableName]);
            $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($columns as $column) {
                $primaryKey []= $column['Column_Name'];
            }

            $this->primaryKeys[$tableName] = $primaryKey;
        }

        return $this->primaryKeys[$tableName];
    }
}
