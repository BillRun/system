<?php

declare(strict_types=1);

namespace Codeception\Lib\Driver;

use Codeception\Exception\ModuleConfigException;
use Codeception\Exception\ModuleException;
use Exception;
use MongoDB\Database;

class MongoDb
{
    /**
     * @var int
     */
    public const DEFAULT_PORT = 27017;

    private ?Database $dbh;

    private ?string $dbName = null;
    private string $host;
    private string $user;
    private string $password;

    private ?\MongoDB\Client $client = null;

    private string $quiet = '';

    /**
     * Connect to the Mongo server using the MongoDB extension.
     */
    protected function setupMongoDB(string $dsn, array $options): void
    {
        try {
            $this->client = new \MongoDB\Client($dsn, $options);
            $this->dbh    = $this->client->selectDatabase($this->dbName);
        } catch (\MongoDB\Driver\Exception $exception) {
            throw new ModuleException($this, sprintf('Failed to open Mongo connection: %s', $exception->getMessage()));
        }
    }

    /**
     * Clean up the Mongo database using the MongoDB extension.
     */
    protected function cleanupMongoDB(): void
    {
        try {
            $this->dbh->drop();
        } catch (\MongoDB\Driver\Exception $e) {
            throw new Exception(sprintf('Failed to drop the DB: %s', $e->getMessage()), $e->getCode(), $e);
        }
    }

    /**
     * $dsn has to contain db_name after the host. E.g. "mongodb://localhost:27017/mongo_test_db"
     *
     * @static
     *
     * @throws ModuleConfigException
     * @throws Exception
     */
    public function __construct(string $dsn, string $user, string $password)
    {
        /* defining DB name */
        $this->dbName = preg_replace('#\?.*#', '', substr($dsn, strrpos($dsn, '/') + 1));

        if (strlen($this->dbName) == 0) {
            throw new ModuleConfigException($this, 'Please specify valid $dsn with DB name after the host:port');
        }

        /* defining host */
        if (strpos($dsn, 'mongodb://') !== false) {
            $this->host = str_replace('mongodb://', '', preg_replace('#\?.*#', '', $dsn));
        } else {
            $this->host = $dsn;
        }
        $this->host = rtrim(str_replace($this->dbName, '', $this->host), '/');

        $options = [
            'connect' => true
        ];

        if ($user && $password) {
            $options += [
                'username' => $user,
                'password' => $password
            ];
        }

        $this->setupMongoDB($dsn, $options);
        $this->user = $user;
        $this->password = $password;
    }

    /**
     * @static
     */
    public static function create(string $dsn, string $user, string $password): \Codeception\Lib\Driver\MongoDb
    {
        return new MongoDb($dsn, $user, $password);
    }

    public function cleanup(): void
    {
        $this->cleanupMongoDB();
    }

    /**
     * dump file has to be a javascript document where one can use all the mongo shell's commands
     * just FYI: this file can be easily created be RockMongo's export button
     */
    public function load(string $dumpFile): void
    {
        $cmd = sprintf(
            'mongo %s %s%s',
            $this->host . '/' . $this->dbName,
            $this->createUserPasswordCmdString(),
            escapeshellarg($dumpFile)
        );
        shell_exec($cmd);
    }

    public function loadFromMongoDump(string $dumpFile): void
    {
        [$host, $port] = $this->getHostPort();
        $cmd = sprintf(
            "mongorestore %s --host %s --port %s -d %s %s %s",
            $this->quiet,
            $host,
            $port,
            $this->dbName,
            $this->createUserPasswordCmdString(),
            escapeshellarg($dumpFile)
        );
        shell_exec($cmd);
    }

    public function loadFromTarGzMongoDump(string $dumpFile): void
    {
        [$host, $port] = $this->getHostPort();
        $getDirCmd = sprintf(
            "tar -tf %s | awk 'BEGIN { FS = \"/\" } ; { print $1 }' | uniq",
            escapeshellarg($dumpFile)
        );
        $dirCountCmd = $getDirCmd . ' | wc -l';
        if (trim(shell_exec($dirCountCmd)) !== '1') {
            throw new ModuleException(
                $this,
                'Archive MUST contain single directory with db dump'
            );
        }
        $dirName = trim(shell_exec($getDirCmd));
        $cmd = sprintf(
            'tar -xzf %s && mongorestore %s --host %s --port %s -d %s %s %s && rm -r %s',
            escapeshellarg($dumpFile),
            $this->quiet,
            $host,
            $port,
            $this->dbName,
            $this->createUserPasswordCmdString(),
            $dirName,
            $dirName
        );
        shell_exec($cmd);
    }

    private function createUserPasswordCmdString(): string
    {
        if ($this->user && $this->password) {
            return sprintf(
                '--username %s --password %s ',
                $this->user,
                $this->password
            );
        }
        return '';
    }

    /**
     * @return \Codeception\Lib\Driver\MongoDb|\MongoDB\Database|null
     */
    public function getDbh()
    {
        return $this->dbh;
    }

    public function setDatabase(string $dbName): void
    {
        $this->dbh = $this->client->selectDatabase($dbName);
    }

    public function getDbHash()
    {
        $result = $this->dbh->command(['dbHash' => 1]);

        if (!is_array($result)) {
            $result = iterator_to_array($result);
        }

        return $result[0]->md5 ?? null;
    }

    /**
     * @return string[]|int[]
     */
    private function getHostPort(): array
    {
        $hostPort = explode(':', $this->host);
        if (count($hostPort) === 2) {
            return $hostPort;
        }
        if (count($hostPort) === 1) {
            return [$hostPort[0], self::DEFAULT_PORT];
        }
        throw new ModuleException($this, '$dsn MUST be like (mongodb://)<host>:<port>/<db name>');
    }

    public function setQuiet(bool $quiet): void
    {
        $this->quiet = $quiet ? '--quiet' : '';
    }
}
