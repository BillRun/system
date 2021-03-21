<?php

namespace MongoDB\Tests\Database;

use MongoDB\Database;
use MongoDB\Driver\BulkWrite;
use MongoDB\Driver\Cursor;
use MongoDB\Driver\ReadConcern;
use MongoDB\Driver\ReadPreference;
use MongoDB\Driver\WriteConcern;
use MongoDB\Exception\InvalidArgumentException;
use MongoDB\Operation\CreateIndexes;
use function array_key_exists;
use function current;

/**
 * Functional tests for the Database class.
 */
class DatabaseFunctionalTest extends FunctionalTestCase
{
    /**
     * @dataProvider provideInvalidDatabaseNames
     */
    public function testConstructorDatabaseNameArgument($databaseName)
    {
        $this->expectException(InvalidArgumentException::class);
        // TODO: Move to unit test once ManagerInterface can be mocked (PHPC-378)
        new Database($this->manager, $databaseName);
    }

    public function provideInvalidDatabaseNames()
    {
        return [
            [null],
            [''],
        ];
    }

    /**
     * @dataProvider provideInvalidConstructorOptions
     */
    public function testConstructorOptionTypeChecks(array $options)
    {
        $this->expectException(InvalidArgumentException::class);
        new Database($this->manager, $this->getDatabaseName(), $options);
    }

    public function provideInvalidConstructorOptions()
    {
        $options = [];

        foreach ($this->getInvalidReadConcernValues() as $value) {
            $options[][] = ['readConcern' => $value];
        }

        foreach ($this->getInvalidReadPreferenceValues() as $value) {
            $options[][] = ['readPreference' => $value];
        }

        foreach ($this->getInvalidArrayValues() as $value) {
            $options[][] = ['typeMap' => $value];
        }

        foreach ($this->getInvalidWriteConcernValues() as $value) {
            $options[][] = ['writeConcern' => $value];
        }

        return $options;
    }

    public function testGetManager()
    {
        $this->assertSame($this->manager, $this->database->getManager());
    }

    public function testToString()
    {
        $this->assertEquals($this->getDatabaseName(), (string) $this->database);
    }

    public function getGetDatabaseName()
    {
        $this->assertEquals($this->getDatabaseName(), $this->database->getDatabaseName());
    }

    public function testCommand()
    {
        $command = ['isMaster' => 1];
        $options = [
            'readPreference' => new ReadPreference(ReadPreference::RP_PRIMARY),
        ];

        $cursor = $this->database->command($command, $options);

        $this->assertInstanceOf(Cursor::class, $cursor);
        $commandResult = current($cursor->toArray());

        $this->assertCommandSucceeded($commandResult);
        $this->assertObjectHasAttribute('ismaster', $commandResult);
        $this->assertTrue($commandResult->ismaster);
    }

    public function testCommandDoesNotInheritReadPreference()
    {
        if (! $this->isReplicaSet()) {
            $this->markTestSkipped('Test only applies to replica sets');
        }

        $this->database = new Database($this->manager, $this->getDatabaseName(), ['readPreference' => new ReadPreference(ReadPreference::RP_SECONDARY)]);

        $command = ['ping' => 1];

        $cursor = $this->database->command($command);

        $this->assertInstanceOf(Cursor::class, $cursor);
        $this->assertTrue($cursor->getServer()->isPrimary());
    }

    public function testCommandAppliesTypeMapToCursor()
    {
        $command = ['isMaster' => 1];
        $options = [
            'readPreference' => new ReadPreference(ReadPreference::RP_PRIMARY),
            'typeMap' => ['root' => 'array'],
        ];

        $cursor = $this->database->command($command, $options);

        $this->assertInstanceOf(Cursor::class, $cursor);
        $commandResult = current($cursor->toArray());

        $this->assertCommandSucceeded($commandResult);
        $this->assertIsArray($commandResult);
        $this->assertArrayHasKey('ismaster', $commandResult);
        $this->assertTrue($commandResult['ismaster']);
    }

    /**
     * @dataProvider provideInvalidDocumentValues
     */
    public function testCommandCommandArgumentTypeCheck($command)
    {
        $this->expectException(InvalidArgumentException::class);
        $this->database->command($command);
    }

    public function testDrop()
    {
        $bulkWrite = new BulkWrite();
        $bulkWrite->insert(['x' => 1]);

        $writeResult = $this->manager->executeBulkWrite($this->getNamespace(), $bulkWrite);
        $this->assertEquals(1, $writeResult->getInsertedCount());

        $commandResult = $this->database->drop();
        $this->assertCommandSucceeded($commandResult);
        $this->assertCollectionCount($this->getNamespace(), 0);
    }

    public function testGetSelectsCollectionAndInheritsOptions()
    {
        $databaseOptions = ['writeConcern' => new WriteConcern(WriteConcern::MAJORITY)];

        $database = new Database($this->manager, $this->getDatabaseName(), $databaseOptions);
        $collection = $database->{$this->getCollectionName()};
        $debug = $collection->__debugInfo();

        $this->assertSame($this->manager, $debug['manager']);
        $this->assertSame($this->getDatabaseName(), $debug['databaseName']);
        $this->assertSame($this->getCollectionName(), $debug['collectionName']);
        $this->assertInstanceOf(WriteConcern::class, $debug['writeConcern']);
        $this->assertSame(WriteConcern::MAJORITY, $debug['writeConcern']->getW());
    }

    public function testModifyCollection()
    {
        $this->database->createCollection($this->getCollectionName());

        $indexes = [['key' => ['lastAccess' => 1], 'expireAfterSeconds' => 3]];
        $createIndexes = new CreateIndexes($this->getDatabaseName(), $this->getCollectionName(), $indexes);
        $createIndexes->execute($this->getPrimaryServer());

        $commandResult = $this->database->modifyCollection(
            $this->getCollectionName(),
            ['index' => ['keyPattern' => ['lastAccess' => 1], 'expireAfterSeconds' => 1000]],
            ['typeMap' => ['root' => 'array', 'document' => 'array']]
        );
        $this->assertCommandSucceeded($commandResult);

        $commandResult = (array) $commandResult;

        if (array_key_exists('raw', $commandResult)) {
            /* Sharded environment, where we only assert if a shard had a successful update. For
             * non-primary shards that don't have chunks for the collection, the result contains a
             * "ns does not exist" error. */
            foreach ($commandResult['raw'] as $shard) {
                if (array_key_exists('ok', $shard) && $shard['ok'] == 1) {
                    $this->assertSame(3, $shard['expireAfterSeconds_old']);
                    $this->assertSame(1000, $shard['expireAfterSeconds_new']);
                }
            }
        } else {
            $this->assertSame(3, $commandResult['expireAfterSeconds_old']);
            $this->assertSame(1000, $commandResult['expireAfterSeconds_new']);
        }
    }

    public function testSelectCollectionInheritsOptions()
    {
        $databaseOptions = [
            'readConcern' => new ReadConcern(ReadConcern::LOCAL),
            'readPreference' => new ReadPreference(ReadPreference::RP_SECONDARY_PREFERRED),
            'typeMap' => ['root' => 'array'],
            'writeConcern' => new WriteConcern(WriteConcern::MAJORITY),
        ];

        $database = new Database($this->manager, $this->getDatabaseName(), $databaseOptions);
        $collection = $database->selectCollection($this->getCollectionName());
        $debug = $collection->__debugInfo();

        $this->assertSame($this->manager, $debug['manager']);
        $this->assertSame($this->getDatabaseName(), $debug['databaseName']);
        $this->assertSame($this->getCollectionName(), $debug['collectionName']);
        $this->assertInstanceOf(ReadConcern::class, $debug['readConcern']);
        $this->assertSame(ReadConcern::LOCAL, $debug['readConcern']->getLevel());
        $this->assertInstanceOf(ReadPreference::class, $debug['readPreference']);
        $this->assertSame(ReadPreference::RP_SECONDARY_PREFERRED, $debug['readPreference']->getMode());
        $this->assertIsArray($debug['typeMap']);
        $this->assertSame(['root' => 'array'], $debug['typeMap']);
        $this->assertInstanceOf(WriteConcern::class, $debug['writeConcern']);
        $this->assertSame(WriteConcern::MAJORITY, $debug['writeConcern']->getW());
    }

    public function testSelectCollectionPassesOptions()
    {
        $collectionOptions = [
            'readConcern' => new ReadConcern(ReadConcern::LOCAL),
            'readPreference' => new ReadPreference(ReadPreference::RP_SECONDARY_PREFERRED),
            'typeMap' => ['root' => 'array'],
            'writeConcern' => new WriteConcern(WriteConcern::MAJORITY),
        ];

        $collection = $this->database->selectCollection($this->getCollectionName(), $collectionOptions);
        $debug = $collection->__debugInfo();

        $this->assertInstanceOf(ReadConcern::class, $debug['readConcern']);
        $this->assertSame(ReadConcern::LOCAL, $debug['readConcern']->getLevel());
        $this->assertInstanceOf(ReadPreference::class, $debug['readPreference']);
        $this->assertSame(ReadPreference::RP_SECONDARY_PREFERRED, $debug['readPreference']->getMode());
        $this->assertIsArray($debug['typeMap']);
        $this->assertSame(['root' => 'array'], $debug['typeMap']);
        $this->assertInstanceOf(WriteConcern::class, $debug['writeConcern']);
        $this->assertSame(WriteConcern::MAJORITY, $debug['writeConcern']->getW());
    }

    public function testSelectGridFSBucketInheritsOptions()
    {
        $databaseOptions = [
            'readConcern' => new ReadConcern(ReadConcern::LOCAL),
            'readPreference' => new ReadPreference(ReadPreference::RP_SECONDARY_PREFERRED),
            'writeConcern' => new WriteConcern(WriteConcern::MAJORITY),
        ];

        $database = new Database($this->manager, $this->getDatabaseName(), $databaseOptions);
        $bucket = $database->selectGridFSBucket();
        $debug = $bucket->__debugInfo();

        $this->assertSame($this->manager, $debug['manager']);
        $this->assertSame($this->getDatabaseName(), $debug['databaseName']);
        $this->assertSame('fs', $debug['bucketName']);
        $this->assertSame(261120, $debug['chunkSizeBytes']);
        $this->assertInstanceOf(ReadConcern::class, $debug['readConcern']);
        $this->assertSame(ReadConcern::LOCAL, $debug['readConcern']->getLevel());
        $this->assertInstanceOf(ReadPreference::class, $debug['readPreference']);
        $this->assertSame(ReadPreference::RP_SECONDARY_PREFERRED, $debug['readPreference']->getMode());
        $this->assertInstanceOf(WriteConcern::class, $debug['writeConcern']);
        $this->assertSame(WriteConcern::MAJORITY, $debug['writeConcern']->getW());
    }

    public function testSelectGridFSBucketPassesOptions()
    {
        $bucketOptions = [
            'bucketName' => 'custom_fs',
            'chunkSizeBytes' => 8192,
            'readConcern' => new ReadConcern(ReadConcern::LOCAL),
            'readPreference' => new ReadPreference(ReadPreference::RP_SECONDARY_PREFERRED),
            'writeConcern' => new WriteConcern(WriteConcern::MAJORITY),
        ];

        $database = new Database($this->manager, $this->getDatabaseName());
        $bucket = $database->selectGridFSBucket($bucketOptions);
        $debug = $bucket->__debugInfo();

        $this->assertSame($this->getDatabaseName(), $debug['databaseName']);
        $this->assertSame('custom_fs', $debug['bucketName']);
        $this->assertSame(8192, $debug['chunkSizeBytes']);
        $this->assertInstanceOf(ReadConcern::class, $debug['readConcern']);
        $this->assertSame(ReadConcern::LOCAL, $debug['readConcern']->getLevel());
        $this->assertInstanceOf(ReadPreference::class, $debug['readPreference']);
        $this->assertSame(ReadPreference::RP_SECONDARY_PREFERRED, $debug['readPreference']->getMode());
        $this->assertInstanceOf(WriteConcern::class, $debug['writeConcern']);
        $this->assertSame(WriteConcern::MAJORITY, $debug['writeConcern']->getW());
    }

    public function testWithOptionsInheritsOptions()
    {
        $databaseOptions = [
            'readConcern' => new ReadConcern(ReadConcern::LOCAL),
            'readPreference' => new ReadPreference(ReadPreference::RP_SECONDARY_PREFERRED),
            'typeMap' => ['root' => 'array'],
            'writeConcern' => new WriteConcern(WriteConcern::MAJORITY),
        ];

        $database = new Database($this->manager, $this->getDatabaseName(), $databaseOptions);
        $clone = $database->withOptions();
        $debug = $clone->__debugInfo();

        $this->assertSame($this->manager, $debug['manager']);
        $this->assertSame($this->getDatabaseName(), $debug['databaseName']);
        $this->assertInstanceOf(ReadConcern::class, $debug['readConcern']);
        $this->assertSame(ReadConcern::LOCAL, $debug['readConcern']->getLevel());
        $this->assertInstanceOf(ReadPreference::class, $debug['readPreference']);
        $this->assertSame(ReadPreference::RP_SECONDARY_PREFERRED, $debug['readPreference']->getMode());
        $this->assertIsArray($debug['typeMap']);
        $this->assertSame(['root' => 'array'], $debug['typeMap']);
        $this->assertInstanceOf(WriteConcern::class, $debug['writeConcern']);
        $this->assertSame(WriteConcern::MAJORITY, $debug['writeConcern']->getW());
    }

    public function testWithOptionsPassesOptions()
    {
        $databaseOptions = [
            'readConcern' => new ReadConcern(ReadConcern::LOCAL),
            'readPreference' => new ReadPreference(ReadPreference::RP_SECONDARY_PREFERRED),
            'typeMap' => ['root' => 'array'],
            'writeConcern' => new WriteConcern(WriteConcern::MAJORITY),
        ];

        $clone = $this->database->withOptions($databaseOptions);
        $debug = $clone->__debugInfo();

        $this->assertInstanceOf(ReadConcern::class, $debug['readConcern']);
        $this->assertSame(ReadConcern::LOCAL, $debug['readConcern']->getLevel());
        $this->assertInstanceOf(ReadPreference::class, $debug['readPreference']);
        $this->assertSame(ReadPreference::RP_SECONDARY_PREFERRED, $debug['readPreference']->getMode());
        $this->assertIsArray($debug['typeMap']);
        $this->assertSame(['root' => 'array'], $debug['typeMap']);
        $this->assertInstanceOf(WriteConcern::class, $debug['writeConcern']);
        $this->assertSame(WriteConcern::MAJORITY, $debug['writeConcern']->getW());
    }
}
