<?php

namespace MongoDB\Tests\Collection;

use Closure;
use MongoDB\BSON\Javascript;
use MongoDB\Collection;
use MongoDB\Database;
use MongoDB\Driver\BulkWrite;
use MongoDB\Driver\ReadConcern;
use MongoDB\Driver\ReadPreference;
use MongoDB\Driver\WriteConcern;
use MongoDB\Exception\InvalidArgumentException;
use MongoDB\Exception\UnsupportedException;
use MongoDB\MapReduceResult;
use MongoDB\Operation\Count;
use MongoDB\Tests\CommandObserver;

use function array_filter;
use function call_user_func;
use function is_scalar;
use function json_encode;
use function strchr;
use function usort;
use function version_compare;

/**
 * Functional tests for the Collection class.
 */
class CollectionFunctionalTest extends FunctionalTestCase
{
    /**
     * @dataProvider provideInvalidDatabaseAndCollectionNames
     */
    public function testConstructorDatabaseNameArgument($databaseName): void
    {
        $this->expectException(InvalidArgumentException::class);
        // TODO: Move to unit test once ManagerInterface can be mocked (PHPC-378)
        new Collection($this->manager, $databaseName, $this->getCollectionName());
    }

    /**
     * @dataProvider provideInvalidDatabaseAndCollectionNames
     */
    public function testConstructorCollectionNameArgument($collectionName): void
    {
        $this->expectException(InvalidArgumentException::class);
        // TODO: Move to unit test once ManagerInterface can be mocked (PHPC-378)
        new Collection($this->manager, $this->getDatabaseName(), $collectionName);
    }

    public function provideInvalidDatabaseAndCollectionNames()
    {
        return [
            [null],
            [''],
        ];
    }

    /**
     * @dataProvider provideInvalidConstructorOptions
     */
    public function testConstructorOptionTypeChecks(array $options): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Collection($this->manager, $this->getDatabaseName(), $this->getCollectionName(), $options);
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

    public function testGetManager(): void
    {
        $this->assertSame($this->manager, $this->collection->getManager());
    }

    public function testToString(): void
    {
        $this->assertEquals($this->getNamespace(), (string) $this->collection);
    }

    public function getGetCollectionName(): void
    {
        $this->assertEquals($this->getCollectionName(), $this->collection->getCollectionName());
    }

    public function getGetDatabaseName(): void
    {
        $this->assertEquals($this->getDatabaseName(), $this->collection->getDatabaseName());
    }

    public function testGetNamespace(): void
    {
        $this->assertEquals($this->getNamespace(), $this->collection->getNamespace());
    }

    public function testAggregateWithinTransaction(): void
    {
        $this->skipIfTransactionsAreNotSupported();

        // Collection must be created before the transaction starts
        $this->createCollection();

        $session = $this->manager->startSession();
        $session->startTransaction();

        try {
            $this->createFixtures(3, ['session' => $session]);

            $cursor = $this->collection->aggregate(
                [['$match' => ['_id' => ['$lt' => 3]]]],
                ['session' => $session]
            );

            $expected = [
                ['_id' => 1, 'x' => 11],
                ['_id' => 2, 'x' => 22],
            ];

            $this->assertSameDocuments($expected, $cursor);

            $session->commitTransaction();
        } finally {
            $session->endSession();
        }
    }

    public function testCreateIndexSplitsCommandOptions(): void
    {
        if (version_compare($this->getServerVersion(), '3.6.0', '<')) {
            $this->markTestSkipped('Sessions are not supported');
        }

        (new CommandObserver())->observe(
            function (): void {
                $this->collection->createIndex(
                    ['x' => 1],
                    [
                        'maxTimeMS' => 10000,
                        'session' => $this->manager->startSession(),
                        'sparse' => true,
                        'unique' => true,
                        'writeConcern' => new WriteConcern(1),
                    ]
                );
            },
            function (array $event): void {
                $command = $event['started']->getCommand();
                $this->assertObjectHasAttribute('lsid', $command);
                $this->assertObjectHasAttribute('maxTimeMS', $command);
                $this->assertObjectHasAttribute('writeConcern', $command);
                $this->assertObjectHasAttribute('sparse', $command->indexes[0]);
                $this->assertObjectHasAttribute('unique', $command->indexes[0]);
            }
        );
    }

    /**
     * @dataProvider provideTypeMapOptionsAndExpectedDocuments
     */
    public function testDistinctWithTypeMap(array $typeMap, array $expectedDocuments): void
    {
        $bulkWrite = new BulkWrite(['ordered' => true]);
        $bulkWrite->insert([
            'x' => (object) ['foo' => 'bar'],
        ]);
        $bulkWrite->insert(['x' => 4]);
        $bulkWrite->insert([
            'x' => (object) ['foo' => ['foo' => 'bar']],
        ]);
        $this->manager->executeBulkWrite($this->getNamespace(), $bulkWrite);

        $values = $this->collection->withOptions(['typeMap' => $typeMap])->distinct('x');

        /* This sort callable sorts all scalars to the front of the list. All
         * non-scalar values are sorted by running json_encode on them and
         * comparing their string representations.
         */
        $sort = function ($a, $b) {
            if (is_scalar($a) && ! is_scalar($b)) {
                return -1;
            }

            if (! is_scalar($a)) {
                if (is_scalar($b)) {
                    return 1;
                }

                $a = json_encode($a);
                $b = json_encode($b);
            }

            return $a < $b ? -1 : 1;
        };

        usort($expectedDocuments, $sort);
        usort($values, $sort);

        $this->assertEquals($expectedDocuments, $values);
    }

    public function provideTypeMapOptionsAndExpectedDocuments()
    {
        return [
            'No type map' => [
                ['root' => 'array', 'document' => 'array'],
                [
                    ['foo' => 'bar'],
                    4,
                    ['foo' => ['foo' => 'bar']],
                ],
            ],
            'array/array' => [
                ['root' => 'array', 'document' => 'array'],
                [
                    ['foo' => 'bar'],
                    4,
                    ['foo' => ['foo' => 'bar']],
                ],
            ],
            'object/array' => [
                ['root' => 'object', 'document' => 'array'],
                [
                    (object) ['foo' => 'bar'],
                    4,
                    (object) ['foo' => ['foo' => 'bar']],
                ],
            ],
            'array/stdClass' => [
                ['root' => 'array', 'document' => 'stdClass'],
                [
                    ['foo' => 'bar'],
                    4,
                    ['foo' => (object) ['foo' => 'bar']],
                ],
            ],
        ];
    }

    public function testDrop(): void
    {
        $writeResult = $this->collection->insertOne(['x' => 1]);
        $this->assertEquals(1, $writeResult->getInsertedCount());

        $commandResult = $this->collection->drop();
        $this->assertCommandSucceeded($commandResult);
        $this->assertCollectionDoesNotExist($this->getCollectionName());
    }

    /**
     * @todo Move this to a unit test once Manager can be mocked
     */
    public function testDropIndexShouldNotAllowWildcardCharacter(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->collection->dropIndex('*');
    }

    public function testExplain(): void
    {
        $this->createFixtures(3);

        $operation = new Count($this->getDatabaseName(), $this->getCollectionName(), ['x' => ['$gte' => 1]], []);

        $result = $this->collection->explain($operation);

        $this->assertArrayHasKey('queryPlanner', $result);
    }

    public function testFindOne(): void
    {
        $this->createFixtures(5);

        $filter = ['_id' => ['$lt' => 5]];
        $options = [
            'skip' => 1,
            'sort' => ['x' => -1],
        ];

        $expected = ['_id' => 3, 'x' => 33];

        $this->assertSameDocument($expected, $this->collection->findOne($filter, $options));
    }

    public function testFindWithinTransaction(): void
    {
        $this->skipIfTransactionsAreNotSupported();

        // Collection must be created before the transaction starts
        $this->createCollection();

        $session = $this->manager->startSession();
        $session->startTransaction();

        try {
            $this->createFixtures(3, ['session' => $session]);

            $cursor = $this->collection->find(
                ['_id' => ['$lt' => 3]],
                ['session' => $session]
            );

            $expected = [
                ['_id' => 1, 'x' => 11],
                ['_id' => 2, 'x' => 22],
            ];

            $this->assertSameDocuments($expected, $cursor);

            $session->commitTransaction();
        } finally {
            $session->endSession();
        }
    }

    public function testRenameToSameDatabase(): void
    {
        $toCollectionName = $this->getCollectionName() . '.renamed';
        $toCollection = new Collection($this->manager, $this->getDatabaseName(), $toCollectionName);

        $writeResult = $this->collection->insertOne(['_id' => 1]);
        $this->assertEquals(1, $writeResult->getInsertedCount());

        $commandResult = $this->collection->rename($toCollectionName, null, ['dropTarget' => true]);
        $this->assertCommandSucceeded($commandResult);
        $this->assertCollectionDoesNotExist($this->getCollectionName());
        $this->assertCollectionExists($toCollectionName);

        $this->assertSameDocument(['_id' => 1], $toCollection->findOne());
        $toCollection->drop();
    }

    public function testRenameToDifferentDatabase(): void
    {
        $toDatabaseName = $this->getDatabaseName() . '_renamed';
        $toDatabase = new Database($this->manager, $toDatabaseName);

        /* When renaming an unsharded collection, mongos requires the source
        * and target database to both exist on the primary shard. In practice,
        * this means we need to create the target database explicitly.
        * See: https://docs.mongodb.com/manual/reference/command/renameCollection/#unsharded-collections
        */
        if ($this->isShardedCluster()) {
            $toDatabase->foo->insertOne(['_id' => 1]);
        }

        $toCollectionName = $this->getCollectionName() . '.renamed';
        $toCollection = new Collection($this->manager, $toDatabaseName, $toCollectionName);

        $writeResult = $this->collection->insertOne(['_id' => 1]);
        $this->assertEquals(1, $writeResult->getInsertedCount());

        $commandResult = $this->collection->rename($toCollectionName, $toDatabaseName);
        $this->assertCommandSucceeded($commandResult);
        $this->assertCollectionDoesNotExist($this->getCollectionName());
        $this->assertCollectionExists($toCollectionName, $toDatabaseName);

        $this->assertSameDocument(['_id' => 1], $toCollection->findOne());

        $toDatabase->drop();
    }

    public function testWithOptionsInheritsOptions(): void
    {
        $collectionOptions = [
            'readConcern' => new ReadConcern(ReadConcern::LOCAL),
            'readPreference' => new ReadPreference(ReadPreference::RP_SECONDARY_PREFERRED),
            'typeMap' => ['root' => 'array'],
            'writeConcern' => new WriteConcern(WriteConcern::MAJORITY),
        ];

        $collection = new Collection($this->manager, $this->getDatabaseName(), $this->getCollectionName(), $collectionOptions);
        $clone = $collection->withOptions();
        $debug = $clone->__debugInfo();

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

    public function testWithOptionsPassesOptions(): void
    {
        $collectionOptions = [
            'readConcern' => new ReadConcern(ReadConcern::LOCAL),
            'readPreference' => new ReadPreference(ReadPreference::RP_SECONDARY_PREFERRED),
            'typeMap' => ['root' => 'array'],
            'writeConcern' => new WriteConcern(WriteConcern::MAJORITY),
        ];

        $clone = $this->collection->withOptions($collectionOptions);
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

    /**
     * @group matrix-testing-exclude-server-4.4-driver-4.0
     * @group matrix-testing-exclude-server-4.4-driver-4.2
     * @group matrix-testing-exclude-server-5.0-driver-4.0
     * @group matrix-testing-exclude-server-5.0-driver-4.2
     */
    public function testMapReduce(): void
    {
        $this->createFixtures(3);

        $map = new Javascript('function() { emit(1, this.x); }');
        $reduce = new Javascript('function(key, values) { return Array.sum(values); }');
        $out = ['inline' => 1];

        $result = $this->collection->mapReduce($map, $reduce, $out);

        $this->assertInstanceOf(MapReduceResult::class, $result);
        $expected = [
            [ '_id' => 1.0, 'value' => 66.0 ],
        ];

        $this->assertSameDocuments($expected, $result);

        if (version_compare($this->getServerVersion(), '4.3.0', '<')) {
            $this->assertGreaterThanOrEqual(0, $result->getExecutionTimeMS());
            $this->assertNotEmpty($result->getCounts());
        }
    }

    public function collectionMethodClosures()
    {
        return [
            [
                function ($collection, $session, $options = []): void {
                    $collection->aggregate(
                        [['$match' => ['_id' => ['$lt' => 3]]]],
                        ['session' => $session] + $options
                    );
                }, 'rw',
            ],

            [
                function ($collection, $session, $options = []): void {
                    $collection->bulkWrite(
                        [['insertOne' => [['test' => 'foo']]]],
                        ['session' => $session] + $options
                    );
                }, 'w',
            ],

            /* Disabled, as count command can't be used in transactions
            [
                function($collection, $session, $options = []) {
                    $collection->count(
                        [],
                        ['session' => $session] + $options
                    );
                }, 'r'
            ],
            */

            [
                function ($collection, $session, $options = []): void {
                    $collection->countDocuments(
                        [],
                        ['session' => $session] + $options
                    );
                }, 'r',
            ],

            /* Disabled, as it's illegal to use createIndex command in transactions
            [
                function($collection, $session, $options = []) {
                    $collection->createIndex(
                        ['test' => 1],
                        ['session' => $session] + $options
                    );
                }, 'w'
            ],
            */

            [
                function ($collection, $session, $options = []): void {
                    $collection->deleteMany(
                        ['test' => 'foo'],
                        ['session' => $session] + $options
                    );
                }, 'w',
            ],

            [
                function ($collection, $session, $options = []): void {
                    $collection->deleteOne(
                        ['test' => 'foo'],
                        ['session' => $session] + $options
                    );
                }, 'w',
            ],

            [
                function ($collection, $session, $options = []): void {
                    $collection->distinct(
                        '_id',
                        [],
                        ['session' => $session] + $options
                    );
                }, 'r',
            ],

            /* Disabled, as it's illegal to use drop command in transactions
            [
                function($collection, $session, $options = []) {
                    $collection->drop(
                        ['session' => $session] + $options
                    );
                }, 'w'
            ],
            */

            /* Disabled, as it's illegal to use dropIndexes command in transactions
            [
                function($collection, $session, $options = []) {
                    $collection->dropIndex(
                        '_id_1',
                        ['session' => $session] + $options
                    );
                }, 'w'
            ], */

            /* Disabled, as it's illegal to use dropIndexes command in transactions
            [
                function($collection, $session, $options = []) {
                    $collection->dropIndexes(
                        ['session' => $session] + $options
                    );
                }, 'w'
            ],
            */

            /* Disabled, as count command can't be used in transactions
            [
                function($collection, $session, $options = []) {
                    $collection->estimatedDocumentCount(
                        ['session' => $session] + $options
                    );
                }, 'r'
            ],
            */

            [
                function ($collection, $session, $options = []): void {
                    $collection->find(
                        ['test' => 'foo'],
                        ['session' => $session] + $options
                    );
                }, 'r',
            ],

            [
                function ($collection, $session, $options = []): void {
                    $collection->findOne(
                        ['test' => 'foo'],
                        ['session' => $session] + $options
                    );
                }, 'r',
            ],

            [
                function ($collection, $session, $options = []): void {
                    $collection->findOneAndDelete(
                        ['test' => 'foo'],
                        ['session' => $session] + $options
                    );
                }, 'w',
            ],

            [
                function ($collection, $session, $options = []): void {
                    $collection->findOneAndReplace(
                        ['test' => 'foo'],
                        [],
                        ['session' => $session] + $options
                    );
                }, 'w',
            ],

            [
                function ($collection, $session, $options = []): void {
                    $collection->findOneAndUpdate(
                        ['test' => 'foo'],
                        ['$set' => ['updated' => 1]],
                        ['session' => $session] + $options
                    );
                }, 'w',
            ],

            [
                function ($collection, $session, $options = []): void {
                    $collection->insertMany(
                        [
                            ['test' => 'foo'],
                            ['test' => 'bar'],
                        ],
                        ['session' => $session] + $options
                    );
                }, 'w',
            ],

            [
                function ($collection, $session, $options = []): void {
                    $collection->insertOne(
                        ['test' => 'foo'],
                        ['session' => $session] + $options
                    );
                }, 'w',
            ],

            /* Disabled, as it's illegal to use listIndexes command in transactions
            [
                function($collection, $session, $options = []) {
                    $collection->listIndexes(
                        ['session' => $session] + $options
                    );
                }, 'r'
            ],
            */

            /* Disabled, as it's illegal to use mapReduce command in transactions
            [
                function($collection, $session, $options = []) {
                    $collection->mapReduce(
                        new \MongoDB\BSON\Javascript('function() { emit(this.state, this.pop); }'),
                        new \MongoDB\BSON\Javascript('function(key, values) { return Array.sum(values) }'),
                        ['inline' => 1],
                        ['session' => $session] + $options
                    );
                }, 'rw'
            ],
            */

            [
                function ($collection, $session, $options = []): void {
                    $collection->replaceOne(
                        ['test' => 'foo'],
                        [],
                        ['session' => $session] + $options
                    );
                }, 'w',
            ],

            [
                function ($collection, $session, $options = []): void {
                    $collection->updateMany(
                        ['test' => 'foo'],
                        ['$set' => ['updated' => 1]],
                        ['session' => $session] + $options
                    );
                }, 'w',
            ],

            [
                function ($collection, $session, $options = []): void {
                    $collection->updateOne(
                        ['test' => 'foo'],
                        ['$set' => ['updated' => 1]],
                        ['session' => $session] + $options
                    );
                }, 'w',
            ],

            /* Disabled, as it's illegal to use change streams in transactions
            [
                function($collection, $session, $options = []) {
                    $collection->watch(
                        [],
                        ['session' => $session] + $options
                    );
                }, 'r'
            ],
            */
        ];
    }

    public function collectionReadMethodClosures()
    {
        return array_filter(
            $this->collectionMethodClosures(),
            function ($rw) {
                if (strchr($rw[1], 'r') !== false) {
                    return true;
                }
            }
        );
    }

    public function collectionWriteMethodClosures()
    {
        return array_filter(
            $this->collectionMethodClosures(),
            function ($rw) {
                if (strchr($rw[1], 'w') !== false) {
                    return true;
                }
            }
        );
    }

    /**
     * @dataProvider collectionMethodClosures
     */
    public function testMethodDoesNotInheritReadWriteConcernInTranasaction(Closure $method): void
    {
        $this->skipIfTransactionsAreNotSupported();

        $this->createCollection();

        $session = $this->manager->startSession();
        $session->startTransaction();

        $collection = $this->collection->withOptions([
            'readConcern' => new ReadConcern(ReadConcern::LOCAL),
            'writeConcern' => new WriteConcern(1),
        ]);

        (new CommandObserver())->observe(
            function () use ($method, $collection, $session): void {
                call_user_func($method, $collection, $session);
            },
            function (array $event): void {
                $this->assertObjectNotHasAttribute('writeConcern', $event['started']->getCommand());
                $this->assertObjectNotHasAttribute('readConcern', $event['started']->getCommand());
            }
        );
    }

    /**
     * @dataProvider collectionWriteMethodClosures
     */
    public function testMethodInTransactionWithWriteConcernOption($method): void
    {
        $this->skipIfTransactionsAreNotSupported();

        $this->createCollection();

        $session = $this->manager->startSession();
        $session->startTransaction();

        $this->expectException(UnsupportedException::class);
        $this->expectExceptionMessage('"writeConcern" option cannot be specified within a transaction');

        try {
            call_user_func($method, $this->collection, $session, ['writeConcern' => new WriteConcern(1)]);
        } finally {
            $session->endSession();
        }
    }

    /**
     * @dataProvider collectionReadMethodClosures
     */
    public function testMethodInTransactionWithReadConcernOption($method): void
    {
        $this->skipIfTransactionsAreNotSupported();

        $this->createCollection();

        $session = $this->manager->startSession();
        $session->startTransaction();

        $this->expectException(UnsupportedException::class);
        $this->expectExceptionMessage('"readConcern" option cannot be specified within a transaction');

        try {
            call_user_func($method, $this->collection, $session, ['readConcern' => new ReadConcern(ReadConcern::LOCAL)]);
        } finally {
            $session->endSession();
        }
    }

    /**
     * Create data fixtures.
     *
     * @param integer $n
     * @param array   $executeBulkWriteOptions
     */
    private function createFixtures(int $n, array $executeBulkWriteOptions = []): void
    {
        $bulkWrite = new BulkWrite(['ordered' => true]);

        for ($i = 1; $i <= $n; $i++) {
            $bulkWrite->insert([
                '_id' => $i,
                'x' => (integer) ($i . $i),
            ]);
        }

        $result = $this->manager->executeBulkWrite($this->getNamespace(), $bulkWrite, $executeBulkWriteOptions);

        $this->assertEquals($n, $result->getInsertedCount());
    }
}
