<?php

declare(strict_types=1);

namespace Codeception\Module;

use Codeception\Configuration;
use Codeception\Exception\ModuleConfigException;
use Codeception\Exception\ModuleException;
use Codeception\Lib\DbPopulator;
use Codeception\Lib\Driver\Db as Driver;
use Codeception\Lib\Interfaces\Db as DbInterface;
use Codeception\Lib\Notification;
use Codeception\Module;
use Codeception\TestInterface;
use Codeception\Util\ActionSequence;
use Exception;
use InvalidArgumentException;
use PDO;
use PDOException;

/**
 * Access a database.
 *
 * The most important function of this module is to clean a database before each test.
 * This module also provides actions to perform checks in a database, e.g. [seeInDatabase()](http://codeception.com/docs/modules/Db#seeInDatabase)
 *
 * In order to have your database populated with data you need a raw SQL dump.
 * Simply put the dump in the `tests/_data` directory (by default) and specify the path in the config.
 * The next time after the database is cleared, all your data will be restored from the dump.
 * Don't forget to include `CREATE TABLE` statements in the dump.
 *
 * Supported and tested databases are:
 *
 * * MySQL
 * * SQLite (i.e. just one file)
 * * PostgreSQL
 *
 * Also available:
 *
 * * MS SQL
 * * Oracle
 *
 * Connection is done by database Drivers, which are stored in the `Codeception\Lib\Driver` namespace.
 * [Check out the drivers](https://github.com/Codeception/Codeception/tree/2.4/src/Codeception/Lib/Driver)
 * if you run into problems loading dumps and cleaning databases.
 *
 * ## Config
 *
 * * dsn *required* - PDO DSN
 * * user *required* - username to access database
 * * password *required* - password
 * * dump - path to database dump
 * * populate: false - whether the the dump should be loaded before the test suite is started
 * * cleanup: false - whether the dump should be reloaded before each test
 * * reconnect: false - whether the module should reconnect to the database before each test
 * * waitlock: 0 - wait lock (in seconds) that the database session should use for DDL statements
 * * ssl_key - path to the SSL key (MySQL specific, @see http://php.net/manual/de/ref.pdo-mysql.php#pdo.constants.mysql-attr-key)
 * * ssl_cert - path to the SSL certificate (MySQL specific, @see http://php.net/manual/de/ref.pdo-mysql.php#pdo.constants.mysql-attr-ssl-cert)
 * * ssl_ca - path to the SSL certificate authority (MySQL specific, @see http://php.net/manual/de/ref.pdo-mysql.php#pdo.constants.mysql-attr-ssl-ca)
 * * ssl_verify_server_cert - disables certificate CN verification (MySQL specific, @see http://php.net/manual/de/ref.pdo-mysql.php)
 * * ssl_cipher - list of one or more permissible ciphers to use for SSL encryption (MySQL specific, @see http://php.net/manual/de/ref.pdo-mysql.php#pdo.constants.mysql-attr-cipher)
 * * databases - include more database configs and switch between them in tests.
 * * initial_queries - list of queries to be executed right after connection to the database has been initiated, i.e. creating the database if it does not exist or preparing the database collation
 * * skip_cleanup_if_failed - Do not perform the cleanup if the tests failed. If this is used, manual cleanup might be required when re-running
 * ## Example
 *
 *     modules:
 *        enabled:
 *           - Db:
 *              dsn: 'mysql:host=localhost;dbname=testdb'
 *              user: 'root'
 *              password: ''
 *              dump: 'tests/_data/dump.sql'
 *              populate: true
 *              cleanup: true
 *              reconnect: true
 *              waitlock: 10
 *              skip_cleanup_if_failed: true
 *              ssl_key: '/path/to/client-key.pem'
 *              ssl_cert: '/path/to/client-cert.pem'
 *              ssl_ca: '/path/to/ca-cert.pem'
 *              ssl_verify_server_cert: false
 *              ssl_cipher: 'AES256-SHA'
 *              initial_queries:
 *                  - 'CREATE DATABASE IF NOT EXISTS temp_db;'
 *                  - 'USE temp_db;'
 *                  - 'SET NAMES utf8;'
 *
 * ## Example with multi-dumps
 *     modules:
 *          enabled:
 *             - Db:
 *                dsn: 'mysql:host=localhost;dbname=testdb'
 *                user: 'root'
 *                password: ''
 *                dump:
 *                   - 'tests/_data/dump.sql'
 *                   - 'tests/_data/dump-2.sql'
 *
 * ## Example with multi-databases
 *
 *     modules:
 *        enabled:
 *           - Db:
 *              dsn: 'mysql:host=localhost;dbname=testdb'
 *              user: 'root'
 *              password: ''
 *              databases:
 *                 db2:
 *                    dsn: 'mysql:host=localhost;dbname=testdb2'
 *                    user: 'userdb2'
 *                    password: ''
 *
 * ## Example with Sqlite
 *
 *     modules:
 *        enabled:
 *           - Db:
 *              dsn: 'sqlite:relative/path/to/sqlite-database.db'
 *              user: ''
 *              password: ''
 *
 * ## SQL data dump
 *
 * There are two ways of loading the dump into your database:
 *
 * ### Populator
 *
 * The recommended approach is to configure a `populator`, an external command to load a dump. Command parameters like host, username, password, database
 * can be obtained from the config and inserted into placeholders:
 *
 * For MySQL:
 *
 * ```yaml
 * modules:
 *    enabled:
 *       - Db:
 *          dsn: 'mysql:host=localhost;dbname=testdb'
 *          user: 'root'
 *          password: ''
 *          dump: 'tests/_data/dump.sql'
 *          populate: true # run populator before all tests
 *          cleanup: true # run populator before each test
 *          populator: 'mysql -u $user -h $host $dbname < $dump'
 * ```
 *
 * For PostgreSQL (using pg_restore)
 *
 * ```
 * modules:
 *    enabled:
 *       - Db:
 *          dsn: 'pgsql:host=localhost;dbname=testdb'
 *          user: 'root'
 *          password: ''
 *          dump: 'tests/_data/db_backup.dump'
 *          populate: true # run populator before all tests
 *          cleanup: true # run populator before each test
 *          populator: 'pg_restore -u $user -h $host -D $dbname < $dump'
 * ```
 *
 *  Variable names are being taken from config and DSN which has a `keyword=value` format, so you should expect to have a variable named as the
 *  keyword with the full value inside it.
 *
 *  PDO dsn elements for the supported drivers:
 *  * MySQL: [PDO_MYSQL DSN](https://secure.php.net/manual/en/ref.pdo-mysql.connection.php)
 *  * SQLite: [PDO_SQLITE DSN](https://secure.php.net/manual/en/ref.pdo-sqlite.connection.php) - use _relative_ path from the project root
 *  * PostgreSQL: [PDO_PGSQL DSN](https://secure.php.net/manual/en/ref.pdo-pgsql.connection.php)
 *  * MSSQL: [PDO_SQLSRV DSN](https://secure.php.net/manual/en/ref.pdo-sqlsrv.connection.php)
 *  * Oracle: [PDO_OCI DSN](https://secure.php.net/manual/en/ref.pdo-oci.connection.php)
 *
 * ### Dump
 *
 * Db module by itself can load SQL dump without external tools by using current database connection.
 * This approach is system-independent, however, it is slower than using a populator and may have parsing issues (see below).
 *
 * Provide a path to SQL file in `dump` config option:
 *
 * ```yaml
 * modules:
 *    enabled:
 *       - Db:
 *          dsn: 'mysql:host=localhost;dbname=testdb'
 *          user: 'root'
 *          password: ''
 *          populate: true # load dump before all tests
 *          cleanup: true # load dump for each test
 *          dump: 'tests/_data/dump.sql'
 * ```
 *
 *  To parse SQL Db file, it should follow this specification:
 *  * Comments are permitted.
 *  * The `dump.sql` may contain multiline statements.
 *  * The delimiter, a semi-colon in this case, must be on the same line as the last statement:
 *
 * ```sql
 * -- Add a few contacts to the table.
 * REPLACE INTO `Contacts` (`created`, `modified`, `status`, `contact`, `first`, `last`) VALUES
 * (NOW(), NOW(), 1, 'Bob Ross', 'Bob', 'Ross'),
 * (NOW(), NOW(), 1, 'Fred Flintstone', 'Fred', 'Flintstone');
 *
 * -- Remove existing orders for testing.
 * DELETE FROM `Order`;
 * ```
 * ## Query generation
 *
 * `seeInDatabase`, `dontSeeInDatabase`, `seeNumRecords`, `grabFromDatabase` and `grabNumRecords` methods
 * accept arrays as criteria. WHERE condition is generated using item key as a field name and
 * item value as a field value.
 *
 * Example:
 * ```php
 * <?php
 * $I->seeInDatabase('users', ['name' => 'Davert', 'email' => 'davert@mail.com']);
 *
 * ```
 * Will generate:
 *
 * ```sql
 * SELECT COUNT(*) FROM `users` WHERE `name` = 'Davert' AND `email` = 'davert@mail.com'
 * ```
 * Since version 2.1.9 it's possible to use LIKE in a condition, as shown here:
 *
 * ```php
 * <?php
 * $I->seeInDatabase('users', ['name' => 'Davert', 'email like' => 'davert%']);
 *
 * ```
 * Will generate:
 *
 * ```sql
 * SELECT COUNT(*) FROM `users` WHERE `name` = 'Davert' AND `email` LIKE 'davert%'
 * ```
 * Null comparisons are also available, as shown here:
 *
 * ```php
 * <?php
 * $I->seeInDatabase('users', ['name' => null, 'email !=' => null]);
 *
 * ```
 * Will generate:
 *
 * ```sql
 * SELECT COUNT(*) FROM `users` WHERE `name` IS NULL AND `email` IS NOT NULL
 * ```
 * ## Public Properties
 * * dbh - contains the PDO connection
 * * driver - contains the Connection Driver
 *
 */
class Db extends Module implements DbInterface
{
    /**
     * @var array
     */
    protected $config = [
        'populate' => false,
        'cleanup' => false,
        'reconnect' => false,
        'waitlock' => 0,
        'dump' => null,
        'populator' => null,
        'skip_cleanup_if_failed' => false,
    ];

    /**
     * @var array
     */
    protected $requiredFields = ['dsn', 'user', 'password'];

    /**
     * @var string
     */
    public const DEFAULT_DATABASE = 'default';

    /**
     * @var Driver[]
     */
    public array $drivers = [];

    /**
     * @var PDO[]
     */
    public array $dbhs = [];

    public array $databasesPopulated = [];

    public array $databasesSql = [];

    protected array $insertedRows = [];

    public string $currentDatabase = self::DEFAULT_DATABASE;

    protected function getDatabases(): array
    {
        $databases = [$this->currentDatabase => $this->config];

        if (!empty($this->config['databases'])) {
            foreach ($this->config['databases'] as $databaseKey => $databaseConfig) {
                $databases[$databaseKey] = array_merge([
                    'populate' => false,
                    'cleanup' => false,
                    'reconnect' => false,
                    'waitlock' => 0,
                    'dump' => null,
                    'populator' => null,
                ], $databaseConfig);
            }
        }

        return $databases;
    }

    protected function connectToDatabases(): void
    {
        foreach ($this->getDatabases() as $databaseKey => $databaseConfig) {
            $this->connect($databaseKey, $databaseConfig);
        }
    }

    protected function cleanUpDatabases(): void
    {
        foreach ($this->getDatabases() as $databaseKey => $databaseConfig) {
            $this->_cleanup($databaseKey, $databaseConfig);
        }
    }

    protected function populateDatabases($configKey): void
    {
        foreach ($this->getDatabases() as $databaseKey => $databaseConfig) {
            if ($databaseConfig[$configKey]) {
                if (!$databaseConfig['populate']) {
                    return;
                }

                if (isset($this->databasesPopulated[$databaseKey]) && $this->databasesPopulated[$databaseKey]) {
                    return;
                }

                $this->_loadDump($databaseKey, $databaseConfig);
            }
        }
    }

    protected function readSqlForDatabases(): void
    {
        foreach ($this->getDatabases() as $databaseKey => $databaseConfig) {
            $this->readSql($databaseKey, $databaseConfig);
        }
    }

    protected function removeInsertedForDatabases(): void
    {
        foreach (array_keys($this->getDatabases()) as $databaseKey) {
            $this->amConnectedToDatabase($databaseKey);
            $this->removeInserted($databaseKey);
        }
    }

    protected function disconnectDatabases(): void
    {
        foreach (array_keys($this->getDatabases()) as $databaseKey) {
            $this->disconnect($databaseKey);
        }
    }

    protected function reconnectDatabases(): void
    {
        foreach ($this->getDatabases() as $databaseKey => $databaseConfig) {
            if ($databaseConfig['reconnect']) {
                $this->disconnect($databaseKey);
                $this->connect($databaseKey, $databaseConfig);
            }
        }
    }

    public function __get($name)
    {
        Notification::deprecate("Properties dbh and driver are deprecated in favor of Db::_getDbh and Db::_getDriver", "Db module");

        if ($name == 'driver') {
            return $this->_getDriver();
        }

        if ($name == 'dbh') {
            return $this->_getDbh();
        }
    }

    public function _getDriver(): Driver
    {
        return $this->drivers[$this->currentDatabase];
    }

    public function _getDbh(): PDO
    {
        return $this->dbhs[$this->currentDatabase];
    }

    /**
     * Make sure you are connected to the right database.
     *
     * ```php
     * <?php
     * $I->seeNumRecords(2, 'users');   //executed on default database
     * $I->amConnectedToDatabase('db_books');
     * $I->seeNumRecords(30, 'books');  //executed on db_books database
     * //All the next queries will be on db_books
     * ```
     *
     * @throws ModuleConfigException
     */
    public function amConnectedToDatabase(string $databaseKey): void
    {
        if (empty($this->getDatabases()[$databaseKey]) && $databaseKey != self::DEFAULT_DATABASE) {
            throw new ModuleConfigException(
                __CLASS__,
                "\nNo database {$databaseKey} in the key databases.\n"
            );
        }

        $this->currentDatabase = $databaseKey;
    }

    /**
     * Can be used with a callback if you don't want to change the current database in your test.
     *
     * ```php
     * <?php
     * $I->seeNumRecords(2, 'users');   //executed on default database
     * $I->performInDatabase('db_books', function($I) {
     *     $I->seeNumRecords(30, 'books');  //executed on db_books database
     * });
     * $I->seeNumRecords(2, 'users');  //executed on default database
     * ```
     * List of actions can be pragmatically built using `Codeception\Util\ActionSequence`:
     *
     * ```php
     * <?php
     * $I->performInDatabase('db_books', ActionSequence::build()
     *     ->seeNumRecords(30, 'books')
     * );
     * ```
     * Alternatively an array can be used:
     *
     * ```php
     * $I->performInDatabase('db_books', ['seeNumRecords' => [30, 'books']]);
     * ```
     *
     * Choose the syntax you like the most and use it,
     *
     * Actions executed from array or ActionSequence will print debug output for actions, and adds an action name to
     * exception on failure.
     *
     * @param $databaseKey
     * @param ActionSequence|array|callable $actions
     * @throws ModuleConfigException
     */
    public function performInDatabase($databaseKey, $actions): void
    {
        $backupDatabase = $this->currentDatabase;
        $this->amConnectedToDatabase($databaseKey);

        if (is_callable($actions)) {
            $actions($this);
            $this->amConnectedToDatabase($backupDatabase);
            return;
        }

        if (is_array($actions)) {
            $actions = ActionSequence::build()->fromArray($actions);
        }

        if (!$actions instanceof ActionSequence) {
            throw new InvalidArgumentException("2nd parameter, actions should be callback, ActionSequence or array");
        }

        $actions->run($this);
        $this->amConnectedToDatabase($backupDatabase);
    }

    public function _initialize(): void
    {
        $this->connectToDatabases();
    }

    public function __destruct()
    {
        $this->disconnectDatabases();
    }

    public function _beforeSuite($settings = []): void
    {
        $this->readSqlForDatabases();
        $this->connectToDatabases();
        $this->cleanUpDatabases();
        $this->populateDatabases('populate');
    }

    private function readSql($databaseKey = null, $databaseConfig = null): void
    {
        if ($databaseConfig['populator']) {
            return;
        }

        if (!$databaseConfig['cleanup'] && !$databaseConfig['populate']) {
            return;
        }

        if (empty($databaseConfig['dump'])) {
            return;
        }

        if (!is_array($databaseConfig['dump'])) {
            $databaseConfig['dump'] = [$databaseConfig['dump']];
        }

        $sql = '';

        foreach ($databaseConfig['dump'] as $filePath) {
            $sql .= $this->readSqlFile($filePath);
        }

        if (!empty($sql)) {
            // split SQL dump into lines
            $this->databasesSql[$databaseKey] = preg_split('#\r\n|\n|\r#', $sql, -1, PREG_SPLIT_NO_EMPTY);
        }
    }

    /**
     * @throws ModuleConfigException
     */
    private function readSqlFile(string $filePath): ?string
    {
        if (!file_exists(Configuration::projectDir() . $filePath)) {
            throw new ModuleConfigException(
                __CLASS__,
                "\nFile with dump doesn't exist.\n"
                . "Please, check path for sql file: "
                . $filePath
            );
        }

        $sql = file_get_contents(Configuration::projectDir() . $filePath);

        // remove C-style comments (except MySQL directives)
        return preg_replace('#/\*(?!!\d+).*?\*/#s', '', $sql);
    }

    private function connect($databaseKey, $databaseConfig): void
    {
        if (!empty($this->drivers[$databaseKey]) && !empty($this->dbhs[$databaseKey])) {
            return;
        }

        $options = [];

        if (array_key_exists('ssl_key', $databaseConfig)
            && !empty($databaseConfig['ssl_key'])
            && defined(PDO::class . '::MYSQL_ATTR_SSL_KEY')
        ) {
            $options[PDO::MYSQL_ATTR_SSL_KEY] = (string) $databaseConfig['ssl_key'];
        }

        if (array_key_exists('ssl_cert', $databaseConfig)
            && !empty($databaseConfig['ssl_cert'])
            && defined(PDO::class . '::MYSQL_ATTR_SSL_CERT')
        ) {
            $options[PDO::MYSQL_ATTR_SSL_CERT] = (string) $databaseConfig['ssl_cert'];
        }

        if (array_key_exists('ssl_ca', $databaseConfig)
            && !empty($databaseConfig['ssl_ca'])
            && defined(PDO::class . '::MYSQL_ATTR_SSL_CA')
        ) {
            $options[PDO::MYSQL_ATTR_SSL_CA] = (string) $databaseConfig['ssl_ca'];
        }

        if (array_key_exists('ssl_cipher', $databaseConfig)
            && !empty($databaseConfig['ssl_cipher'])
            && defined(PDO::class . '::MYSQL_ATTR_SSL_CIPHER')
        ) {
            $options[PDO::MYSQL_ATTR_SSL_CIPHER] = (string) $databaseConfig['ssl_cipher'];
        }

        if (array_key_exists('ssl_verify_server_cert', $databaseConfig)
            && defined(PDO::class . '::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT')
        ) {
            $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = (boolean) $databaseConfig[ 'ssl_verify_server_cert' ];
        }

        try {
            $this->debugSection('Connecting To Db', ['config' => $databaseConfig, 'options' => $options]);
            $this->drivers[$databaseKey] = Driver::create($databaseConfig['dsn'], $databaseConfig['user'], $databaseConfig['password'], $options);
        } catch (PDOException $exception) {
            $message = $exception->getMessage();
            if ($message === 'could not find driver') {
                [$missingDriver, ] = explode(':', $databaseConfig['dsn'], 2);
                $message = sprintf('could not find %s driver', $missingDriver);
            }

            throw new ModuleException(__CLASS__, $message . ' while creating PDO connection');
        }

        if ($databaseConfig['waitlock']) {
            $this->_getDriver()->setWaitLock($databaseConfig['waitlock']);
        }

        if (isset($databaseConfig['initial_queries'])) {
            foreach ($databaseConfig['initial_queries'] as $initialQuery) {
                $this->drivers[$databaseKey]->executeQuery($initialQuery, []);
            }
        }

        $this->debugSection('Db', 'Connected to ' . $databaseKey . ' ' . $this->drivers[$databaseKey]->getDb());
        $this->dbhs[$databaseKey] = $this->drivers[$databaseKey]->getDbh();
    }

    private function disconnect($databaseKey): void
    {
        $this->debugSection('Db', 'Disconnected from ' . $databaseKey);
        $this->dbhs[$databaseKey] = null;
        $this->drivers[$databaseKey] = null;
    }

    public function _before(TestInterface $test): void
    {
        $this->reconnectDatabases();
        $this->amConnectedToDatabase(self::DEFAULT_DATABASE);

        $this->cleanUpDatabases();

        $this->populateDatabases('cleanup');

        parent::_before($test);
    }

    public function _failed(TestInterface $test, $fail)
    {
        foreach ($this->getDatabases() as $databaseKey => $databaseConfig) {
            if ($databaseConfig['skip_cleanup_if_failed'] ?? false) {
                $this->insertedRows[$databaseKey] = [];
            }
        }
    }

    public function _after(TestInterface $test): void
    {
        $this->removeInsertedForDatabases();
        parent::_after($test);
    }

    protected function removeInserted($databaseKey = null): void
    {
        $databaseKey = empty($databaseKey) ?  self::DEFAULT_DATABASE : $databaseKey;

        if (empty($this->insertedRows[$databaseKey])) {
            return;
        }

        foreach (array_reverse($this->insertedRows[$databaseKey]) as $row) {
            try {
                $this->_getDriver()->deleteQueryByCriteria($row['table'], $row['primary']);
            } catch (Exception $e) {
                $this->debug("Couldn't delete record " . json_encode($row['primary'], JSON_THROW_ON_ERROR) ." from {$row['table']}");
            }
        }

        $this->insertedRows[$databaseKey] = [];
    }

    public function _cleanup(string $databaseKey = null, array $databaseConfig = null): void
    {
        $databaseKey = empty($databaseKey) ?  self::DEFAULT_DATABASE : $databaseKey;
        $databaseConfig = empty($databaseConfig) ?  $this->config : $databaseConfig;

        if (!$databaseConfig['populate']) {
            return;
        }

        if (!$databaseConfig['cleanup']) {
            return;
        }

        if (isset($this->databasesPopulated[$databaseKey]) && !$this->databasesPopulated[$databaseKey]) {
            return;
        }

        $dbh = $this->dbhs[$databaseKey];
        if (!$dbh) {
            throw new ModuleConfigException(
                __CLASS__,
                "No connection to database. Remove this module from config if you don't need database repopulation"
            );
        }

        try {
            if (!$this->shouldCleanup($databaseConfig, $databaseKey)) {
                return;
            }

            $this->drivers[$databaseKey]->cleanup();
            $this->databasesPopulated[$databaseKey] = false;
        } catch (Exception $e) {
            throw new ModuleException(__CLASS__, $e->getMessage());
        }
    }

    protected function shouldCleanup(array $databaseConfig, string $databaseKey): bool
    {
        // If using populator and it's not empty, clean up regardless
        if (!empty($databaseConfig['populator'])) {
            return true;
        }

        // If no sql dump for $databaseKey or sql dump is empty, don't clean up
        return !empty($this->databasesSql[$databaseKey]);
    }

    public function _isPopulated()
    {
        return $this->databasesPopulated[$this->currentDatabase];
    }

    public function _loadDump(string $databaseKey = null, array $databaseConfig = null): void
    {
        $databaseKey = empty($databaseKey) ?  self::DEFAULT_DATABASE : $databaseKey;
        $databaseConfig = empty($databaseConfig) ?  $this->config : $databaseConfig;

        if (!empty($databaseConfig['populator'])) {
            $this->loadDumpUsingPopulator($databaseKey, $databaseConfig);
            return;
        }

        $this->loadDumpUsingDriver($databaseKey);
    }

    protected function loadDumpUsingPopulator(string $databaseKey, array $databaseConfig): void
    {
        $populator = new DbPopulator($databaseConfig);
        $this->databasesPopulated[$databaseKey] = $populator->run();
    }

    protected function loadDumpUsingDriver(string $databaseKey): void
    {
        if (!isset($this->databasesSql[$databaseKey])) {
            return;
        }

        if (!$this->databasesSql[$databaseKey]) {
            $this->debugSection('Db', 'No SQL loaded, loading dump skipped');
            return;
        }

        $this->drivers[$databaseKey]->load($this->databasesSql[$databaseKey]);
        $this->databasesPopulated[$databaseKey] = true;
    }

    /**
     * Inserts an SQL record into a database. This record will be erased after the test,
     * unless you've configured "skip_cleanup_if_failed", and the test fails.
     *
     * ```php
     * <?php
     * $I->haveInDatabase('users', array('name' => 'miles', 'email' => 'miles@davis.com'));
     * ```
     */
    public function haveInDatabase(string $table, array $data): int
    {
        $lastInsertId = $this->_insertInDatabase($table, $data);

        $this->addInsertedRow($table, $data, $lastInsertId);

        return $lastInsertId;
    }

    public function _insertInDatabase(string $table, array $data): int
    {
        $query = $this->_getDriver()->insert($table, $data);
        $parameters = array_values($data);
        $this->debugSection('Query', $query);
        $this->debugSection('Parameters', $parameters);
        $this->_getDriver()->executeQuery($query, $parameters);

        try {
            $lastInsertId = (int)$this->_getDriver()->lastInsertId($table);
        } catch (PDOException $e) {
            // ignore errors due to uncommon DB structure,
            // such as tables without _id_seq in PGSQL
            $lastInsertId = 0;
            $this->debugSection('DB error', $e->getMessage());
        }

        return $lastInsertId;
    }

    private function addInsertedRow(string $table, array $row, $id): void
    {
        $primaryKey = $this->_getDriver()->getPrimaryKey($table);
        $primary = [];
        if ($primaryKey !== []) {
            $filledKeys = array_intersect($primaryKey, array_keys($row));
            $missingPrimaryKeyColumns = array_diff_key($primaryKey, $filledKeys);

            if (count($missingPrimaryKeyColumns) === 0) {
                $primary = array_intersect_key($row, array_flip($primaryKey));
            } elseif (count($missingPrimaryKeyColumns) === 1) {
                $primary = array_intersect_key($row, array_flip($primaryKey));
                $missingColumn = reset($missingPrimaryKeyColumns);
                $primary[$missingColumn] = $id;
            } else {
                foreach ($primaryKey as $column) {
                    if (isset($row[$column])) {
                        $primary[$column] = $row[$column];
                    } else {
                        throw new InvalidArgumentException(
                            'Primary key field ' . $column . ' is not set for table ' . $table
                        );
                    }
                }
            }
        } else {
            $primary = $row;
        }

        $this->insertedRows[$this->currentDatabase][] = [
            'table' => $table,
            'primary' => $primary,
        ];
    }

    public function seeInDatabase(string $table, array $criteria = []): void
    {
        $res = $this->countInDatabase($table, $criteria);
        $this->assertGreaterThan(
            0,
            $res,
            'No matching records found for criteria ' . json_encode($criteria, JSON_THROW_ON_ERROR) . ' in table ' . $table
        );
    }

    /**
     * Asserts that the given number of records were found in the database.
     *
     * ```php
     * <?php
     * $I->seeNumRecords(1, 'users', ['name' => 'davert'])
     * ```
     *
     * @param int $expectedNumber Expected number
     * @param string $table Table name
     * @param array $criteria Search criteria [Optional]
     */
    public function seeNumRecords(int $expectedNumber, string $table, array $criteria = []): void
    {
        $actualNumber = $this->countInDatabase($table, $criteria);
        $this->assertSame(
            $expectedNumber,
            $actualNumber,
            sprintf(
                'The number of found rows (%d) does not match expected number %d for criteria %s in table %s',
                $actualNumber,
                $expectedNumber,
                json_encode($criteria, JSON_THROW_ON_ERROR),
                $table
            )
        );
    }

    public function dontSeeInDatabase(string $table, array $criteria = []): void
    {
        $count = $this->countInDatabase($table, $criteria);
        $this->assertLessThan(
            1,
            $count,
            'Unexpectedly found matching records for criteria ' . json_encode($criteria, JSON_THROW_ON_ERROR) . ' in table ' . $table
        );
    }

    /**
     * Count rows in a database
     *
     * @param string $table    Table name
     * @param array  $criteria Search criteria [Optional]
     * @return int
     */
    protected function countInDatabase(string $table, array $criteria = []): int
    {
        return (int) $this->proceedSeeInDatabase($table, 'count(*)', $criteria);
    }

    /**
     * Fetches all values from the column in database.
     * Provide table name, desired column and criteria.
     *
     * @return mixed
     */
    protected function proceedSeeInDatabase(string $table, string $column, array $criteria)
    {
        $query = $this->_getDriver()->select($column, $table, $criteria);
        $parameters = array_values($criteria);
        $this->debugSection('Query', $query);
        if (!empty($parameters)) {
            $this->debugSection('Parameters', $parameters);
        }

        $sth = $this->_getDriver()->executeQuery($query, $parameters);

        return $sth->fetchColumn();
    }

    /**
     * Fetches all values from the column in database.
     * Provide table name, desired column and criteria.
     *
     * ``` php
     * <?php
     * $mails = $I->grabColumnFromDatabase('users', 'email', array('name' => 'RebOOter'));
     * ```
     */
    public function grabColumnFromDatabase(string $table, string $column, array $criteria = []): array
    {
        $query      = $this->_getDriver()->select($column, $table, $criteria);
        $parameters = array_values($criteria);
        $this->debugSection('Query', $query);
        $this->debugSection('Parameters', $parameters);
        $sth = $this->_getDriver()->executeQuery($query, $parameters);

        return $sth->fetchAll(PDO::FETCH_COLUMN, 0);
    }

    /**
     * Fetches a single column value from a database.
     * Provide table name, desired column and criteria.
     *
     * ``` php
     * <?php
     * $mail = $I->grabFromDatabase('users', 'email', array('name' => 'Davert'));
     * ```
     * Comparison expressions can be used as well:
     *
     * ```php
     * <?php
     * $post = $I->grabFromDatabase('posts', ['num_comments >=' => 100]);
     * $user = $I->grabFromDatabase('users', ['email like' => 'miles%']);
     * ```
     *
     * Supported operators: `<`, `>`, `>=`, `<=`, `!=`, `like`.
     *
     * @return mixed Returns a single column value or false
     */
    public function grabFromDatabase(string $table, string $column, array $criteria = [])
    {
        return $this->proceedSeeInDatabase($table, $column, $criteria);
    }

    /**
     * Fetches a whole entry from a database.
     * Make the test fail if the entry is not found.
     * Provide table name, desired column and criteria.
     *
     * ``` php
     * <?php
     * $mail = $I->grabEntryFromDatabase('users', array('name' => 'Davert'));
     * ```
     * Comparison expressions can be used as well:
     *
     * ```php
     * <?php
     * $post = $I->grabEntryFromDatabase('posts', ['num_comments >=' => 100]);
     * $user = $I->grabEntryFromDatabase('users', ['email like' => 'miles%']);
     * ```
     *
     * Supported operators: `<`, `>`, `>=`, `<=`, `!=`, `like`.
     *
     * @return array Returns a single entry value
     * @throws PDOException|Exception
     */
    public function grabEntryFromDatabase(string $table, array $criteria = [])
    {
        $query      = $this->_getDriver()->select('*', $table, $criteria);
        $parameters = array_values($criteria);
        $this->debugSection('Query', $query);
        $this->debugSection('Parameters', $parameters);
        $sth = $this->_getDriver()->executeQuery($query, $parameters);

        $result = $sth->fetch(PDO::FETCH_ASSOC, 0);

        if ($result === false) {
            throw new \AssertionError("No matching row found");
        }

        return $result;
    }

    /**
     * Fetches a set of entries from a database.
     * Provide table name and criteria.
     *
     * ``` php
     * <?php
     * $mail = $I->grabEntriesFromDatabase('users', array('name' => 'Davert'));
     * ```
     * Comparison expressions can be used as well:
     *
     * ```php
     * <?php
     * $post = $I->grabEntriesFromDatabase('posts', ['num_comments >=' => 100]);
     * $user = $I->grabEntriesFromDatabase('users', ['email like' => 'miles%']);
     * ```
     *
     * Supported operators: `<`, `>`, `>=`, `<=`, `!=`, `like`.
     *
     * @return array Returns an array of all matched rows
     * @throws PDOException|Exception
     */
    public function grabEntriesFromDatabase(string $table, array $criteria = [])
    {
        $query      = $this->_getDriver()->select('*', $table, $criteria);
        $parameters = array_values($criteria);
        $this->debugSection('Query', $query);
        $this->debugSection('Parameters', $parameters);
        $sth = $this->_getDriver()->executeQuery($query, $parameters);

        return $sth->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Returns the number of rows in a database
     *
     * @param string $table    Table name
     * @param array  $criteria Search criteria [Optional]
     * @return int
     */
    public function grabNumRecords(string $table, array $criteria = []): int
    {
        return $this->countInDatabase($table, $criteria);
    }

    /**
     * Update an SQL record into a database.
     *
     * ```php
     * <?php
     * $I->updateInDatabase('users', array('isAdmin' => true), array('email' => 'miles@davis.com'));
     * ```
     */
    public function updateInDatabase(string $table, array $data, array $criteria = []): void
    {
        $query = $this->_getDriver()->update($table, $data, $criteria);
        $parameters = [...array_values($data), ...array_values($criteria)];
        $this->debugSection('Query', $query);
        if (!empty($parameters)) {
            $this->debugSection('Parameters', $parameters);
        }

        $this->_getDriver()->executeQuery($query, $parameters);
    }
}
