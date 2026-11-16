<?php

/**
 * BRCD-5431 - the migration of the services include groups to products groups
 * (application/migrations/20260903_001_BRCD-5431.php).
 *
 * Every non counter group of a service becomes a products group of the same key and
 * an include.products_groups entry, the counter groups stay in include.groups, and a
 * group that cannot be migrated aborts the migration so it runs again once fixed.
 *
 * The services are inserted directly to the DB (billapi does not create legacy
 * include.groups anymore) and the migration runs in-process on the whole services
 * collection, which cleanDB() empties before every test.
 *
 * @package         Tests
 * @copyright       Copyright (C) 2012-2026 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */
class productsGroupsMigrationCest
{
    const MIGRATION_FILE = APPLICATION_PATH . '/application/migrations/20260903_001_BRCD-5431.php';

    // =========================================================================
    // LIFECYCLE
    // =========================================================================

    public function _before(ApiTester $I)
    {
        $I->setSettings('usage_types', [
            [
                'usage_type'    => 'call',
                'label'         => 'call',
                'property_type' => 'time',
                'invoice_uom'   => 'seconds',
                'input_uom'     => 'seconds',
            ],
            [
                'usage_type'    => 'data',
                'label'         => 'data',
                'property_type' => 'data',
                'invoice_uom'   => '',
                'input_uom'     => '',
            ],
        ]);
        \Billrun_Config::getInstance()->loadDbConfig();
        $I->cleanDB();
        \Billrun_Factory::db()->productgroupsCollection()->remove([null]);
    }

    // =========================================================================
    // TESTS
    // =========================================================================

    /**
     * A usage based group becomes a products group of the same key, holding its
     * products (as a set) and usage types, open for all times, and the service
     * include.groups is replaced by an include.products_groups entry.
     */
    public function testUsageBasedGroupMigrated(ApiTester $I): void
    {
        $group = $I->uniqueEntityKey('GROUP');
        $service = $this->insertService($I, [
            'groups' => [
                $group => [
                    'rates'       => ['RATE_B', 'RATE_A'],
                    'usage_types' => ['call' => ['unit' => 'minutes']],
                    'value'       => 600,
                ],
            ],
        ]);

        $this->runMigration();

        $productsGroups = $this->getProductsGroups($group);
        $I->assertCount(1, $productsGroups, 'one products group per legacy group');
        $productsGroup = $productsGroups[0];
        $I->assertSame($group, $productsGroup['description']);
        $I->assertSame(['RATE_A', 'RATE_B'], $productsGroup['rates']);
        $I->assertSame(['call'], $productsGroup['usage_types']);
        $I->assertSame('time', $productsGroup['property_type']);
        $I->assertSame(0, $productsGroup['from']->sec, 'the products group is open from the beginning of time');
        $I->assertSame(strtotime(\Models_Entity::UNLIMITED_DATE), $productsGroup['to']->sec, 'the products group is open forever');

        $include = $this->getService($service)['include'];
        $I->assertArrayNotHasKey('groups', $include, 'include.groups is removed when no counter groups are left');
        $I->assertSame([[
            'key'               => $group,
            'account_shared'    => false,
            'account_pool'      => false,
            'quantity_affected' => false,
            'unit'              => 'minutes',
            'value'             => 600,
        ]], $include['products_groups'], 'the value stays in the base unit and the unit is the display one');
    }

    /**
     * A monetary based group (cost, no usage types) becomes a products group without
     * usage types and property type, and an entry holding the cost.
     */
    public function testMonetaryGroupMigrated(ApiTester $I): void
    {
        $group = $I->uniqueEntityKey('GROUP');
        $service = $this->insertService($I, [
            'groups' => [
                $group => ['cost' => 10, 'rates' => ['RATE_A']],
            ],
        ]);

        $this->runMigration();

        $productsGroup = $this->getProductsGroups($group)[0];
        $I->assertSame([], $productsGroup['usage_types']);
        $I->assertArrayNotHasKey('property_type', $productsGroup, 'an untyped products group has no property type');

        $entry = $this->getService($service)['include']['products_groups'][0];
        $I->assertEquals(10, $entry['cost']);
        $I->assertArrayNotHasKey('value', $entry);
        $I->assertArrayNotHasKey('unit', $entry);
    }

    /**
     * The sharing flags are carried to the entry, and a missing flag is false.
     */
    public function testSharingFlagsMigrated(ApiTester $I): void
    {
        $shared = $I->uniqueEntityKey('SHARED');
        $pooled = $I->uniqueEntityKey('POOLED');
        $service = $this->insertService($I, [
            'groups' => [
                $shared => [
                    'rates'          => ['RATE_A'],
                    'usage_types'    => ['call' => ['unit' => 'seconds']],
                    'value'          => 60,
                    'account_shared' => true,
                ],
                $pooled => [
                    'rates'             => ['RATE_B'],
                    'usage_types'       => ['data' => ['unit' => 'mb1000']],
                    'value'             => 1000,
                    'account_shared'    => false,
                    'account_pool'      => true,
                    'quantity_affected' => true,
                ],
            ],
        ]);

        $this->runMigration();

        $entries = $this->getService($service)['include']['products_groups'];
        $I->assertSame([$shared, $pooled], array_column($entries, 'key'), 'the entries keep the groups order');
        $I->assertSame([true, false, false], [$entries[0]['account_shared'], $entries[0]['account_pool'], $entries[0]['quantity_affected']]);
        $I->assertSame([false, true, true], [$entries[1]['account_shared'], $entries[1]['account_pool'], $entries[1]['quantity_affected']]);
        $I->assertSame('data', $this->getProductsGroups($pooled)[0]['property_type']);
    }

    /**
     * Counter groups stay in include.groups as they are, next to the migrated
     * products groups, and no products group is created for them.
     */
    public function testCounterGroupsStayInIncludeGroups(ApiTester $I): void
    {
        $group = $I->uniqueEntityKey('GROUP');
        $counter = $I->uniqueEntityKey('COUNTER');
        $counterGroup = [
            'rates'        => ['RATE_A'],
            'usage_types'  => ['call' => ['unit' => 'seconds']],
            'value'        => \Billrun_Service::UNLIMITED_VALUE,
            'counter_only' => true,
        ];
        $service = $this->insertService($I, [
            'groups' => [
                $counter => $counterGroup,
                $group   => [
                    'rates'       => ['RATE_A'],
                    'usage_types' => ['call' => ['unit' => 'seconds']],
                    'value'       => 60,
                ],
            ],
        ]);

        $this->runMigration();

        $include = $this->getService($service)['include'];
        $I->assertSame([$counter => $counterGroup], $include['groups'], 'only the counter group is left, unchanged');
        $I->assertSame([$group], array_column($include['products_groups'], 'key'));
        $I->assertEmpty($this->getProductsGroups($counter), 'no products group for a counter group');
    }

    /**
     * A service with nothing but counter groups is left as it is.
     */
    public function testCountersOnlyServiceNotChanged(ApiTester $I): void
    {
        $counter = $I->uniqueEntityKey('COUNTER');
        $service = $this->insertService($I, [
            'groups' => [
                $counter => [
                    'rates'        => ['RATE_A'],
                    'usage_types'  => ['call' => ['unit' => 'seconds']],
                    'value'        => \Billrun_Service::UNLIMITED_VALUE,
                    'counter_only' => true,
                ],
            ],
        ]);
        $before = $this->getService($service);

        $this->runMigration();

        $I->assertEquals($before, $this->getService($service));
        $I->assertEmpty($this->getProductsGroups($counter));
    }

    /**
     * Services without include.groups (products groups services, or no include at
     * all) are not touched.
     */
    public function testServicesWithoutGroupsNotChanged(ApiTester $I): void
    {
        $productsGroupsService = $this->insertService($I, [
            'products_groups' => [['key' => 'EXISTING', 'value' => 60, 'unit' => 'seconds']],
        ]);
        $plainService = $this->insertService($I, []);
        $before = [$this->getService($productsGroupsService), $this->getService($plainService)];

        $this->runMigration();

        $I->assertEquals($before, [$this->getService($productsGroupsService), $this->getService($plainService)]);
        $I->assertSame(0, \Billrun_Factory::db()->productgroupsCollection()->query()->count());
    }

    /**
     * Every revision of a service is migrated, sharing one products group.
     */
    public function testAllServiceRevisionsMigrated(ApiTester $I): void
    {
        $group = $I->uniqueEntityKey('GROUP');
        $name = $I->uniqueEntityKey('SERVICE');
        foreach ([[60, '2024-01-01', '2025-01-01'], [120, '2025-01-01', \Models_Entity::UNLIMITED_DATE]] as [$value, $from, $to]) {
            $this->insertService($I, [
                'groups' => [
                    $group => [
                        'rates'       => ['RATE_A'],
                        'usage_types' => ['call' => ['unit' => 'seconds']],
                        'value'       => $value,
                    ],
                ],
            ], ['name' => $name, 'from' => new \Mongodloid_Date(strtotime($from)), 'to' => new \Mongodloid_Date(strtotime($to))]);
        }

        $this->runMigration();

        $revisions = $this->getServiceRevisions($name);
        $I->assertCount(2, $revisions);
        $I->assertSame([60, 120], array_map(function ($revision) {
            return $revision['include']['products_groups'][0]['value'];
        }, $revisions), 'each revision keeps its own value');
        $I->assertCount(1, $this->getProductsGroups($group), 'the revisions share the products group');
    }

    /**
     * The same group in two services, with the same products (in another order) and
     * usage types, is migrated to a single products group.
     */
    public function testSameGroupInTwoServicesSharesProductsGroup(ApiTester $I): void
    {
        $group = $I->uniqueEntityKey('GROUP');
        $services = [];
        foreach ([['RATE_A', 'RATE_B'], ['RATE_B', 'RATE_A']] as $rates) {
            $services[] = $this->insertService($I, [
                'groups' => [
                    $group => [
                        'rates'       => $rates,
                        'usage_types' => ['call' => ['unit' => 'seconds']],
                        'value'       => 60,
                    ],
                ],
            ]);
        }

        $this->runMigration();

        $I->assertCount(1, $this->getProductsGroups($group));
        foreach ($services as $service) {
            $I->assertSame($group, $this->getService($service)['include']['products_groups'][0]['key']);
        }
    }

    /**
     * A group of a key that an existing products group holds with other products
     * aborts the migration: the services migrated before it stay migrated, the
     * failing service is not changed, and once the data is fixed the migration runs
     * again, skipping the services already migrated.
     */
    public function testKeyClashAbortsAndRerunsOnceFixed(ApiTester $I): void
    {
        $group = $I->uniqueEntityKey('GROUP');
        $clashing = $I->uniqueEntityKey('CLASH');
        $migratedFirst = $this->insertService($I, [
            'groups' => [
                $group => [
                    'rates'       => ['RATE_A'],
                    'usage_types' => ['call' => ['unit' => 'seconds']],
                    'value'       => 60,
                ],
            ],
        ]);
        $failing = $this->insertService($I, [
            'groups' => [
                $clashing => [
                    'rates'       => ['RATE_A'],
                    'usage_types' => ['call' => ['unit' => 'seconds']],
                    'value'       => 60,
                ],
            ],
        ]);
        $existing = [
            'key'         => $clashing,
            'description' => $clashing,
            'rates'       => ['RATE_OTHER'],
            'usage_types' => ['call'],
            'from'        => new \Mongodloid_Date(0),
            'to'          => new \Mongodloid_Date(strtotime(\Models_Entity::UNLIMITED_DATE)),
        ];
        \Billrun_Factory::db()->productgroupsCollection()->insert($existing);
        $failingBefore = $this->getService($failing);

        $this->assertMigrationAborts($I, $clashing, 'already holds different products');

        $I->assertArrayHasKey('products_groups', $this->getService($migratedFirst)['include'], 'the service migrated before the abort stays migrated');
        $I->assertEquals($failingBefore, $this->getService($failing), 'the failing service is not changed');

        // fix the data - the existing products group holds the same products
        \Billrun_Factory::db()->productgroupsCollection()->update(['key' => $clashing], ['$set' => ['rates' => ['RATE_A']]]);
        $this->runMigration();

        $include = $this->getService($failing)['include'];
        $I->assertArrayNotHasKey('groups', $include);
        $I->assertSame($clashing, $include['products_groups'][0]['key'], 'the existing products group is reused');
        $I->assertCount(1, $this->getProductsGroups($clashing));
        $I->assertCount(1, $this->getProductsGroups($group), 'the service migrated before is skipped on the rerun');
        $I->assertCount(1, $this->getService($migratedFirst)['include']['products_groups']);
    }

    /**
     * A group with the same key but other usage types also clashes.
     */
    public function testKeyClashOnUsageTypesAborts(ApiTester $I): void
    {
        $group = $I->uniqueEntityKey('GROUP');
        foreach (['call' => 'seconds', 'data' => 'mb1000'] as $usageType => $unit) {
            $this->insertService($I, [
                'groups' => [
                    $group => [
                        'rates'       => ['RATE_A'],
                        'usage_types' => [$usageType => ['unit' => $unit]],
                        'value'       => 60,
                    ],
                ],
            ]);
        }

        $this->assertMigrationAborts($I, $group, 'already holds different products or usage types');
    }

    /**
     * A group with limits (a plugin rule) cannot be a products group - the migration
     * aborts and the service is not changed.
     */
    public function testGroupWithLimitsAborts(ApiTester $I): void
    {
        $group = $I->uniqueEntityKey('GROUP');
        $service = $this->insertService($I, [
            'groups' => [
                $group => [
                    'rates'       => ['RATE_A'],
                    'usage_types' => ['call' => ['unit' => 'seconds']],
                    'value'       => 60,
                    'limits'      => ['destination' => ['972']],
                ],
            ],
        ]);
        $before = $this->getService($service);

        $this->assertMigrationAborts($I, $group, 'the group has limits');

        $I->assertEquals($before, $this->getService($service));
        $I->assertEmpty($this->getProductsGroups($group));
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    /**
     * Run the migration in-process, the way DbmigrateModel does (without recording
     * the task, so it can run again).
     */
    protected function runMigration(): void
    {
        $migration = require self::MIGRATION_FILE;
        $config = [];
        $migration->setContext(\Billrun_Factory::db(), $config);
        $migration->run();
    }

    protected function assertMigrationAborts(ApiTester $I, string $group, string $reason): void
    {
        try {
            $this->runMigration();
        } catch (\Exception $e) {
            $I->assertStringContainsString('cannot migrate group "' . $group . '"', $e->getMessage());
            $I->assertStringContainsString($reason, $e->getMessage());
            return;
        }
        $I->fail('the migration must abort on group ' . $group);
    }

    /**
     * Insert a service directly to the DB.
     *
     * @return string the service name
     */
    protected function insertService(ApiTester $I, array $include, array $override = []): string
    {
        $name = $override['name'] ?? $I->uniqueEntityKey('SERVICE');
        $service = array_merge([
            'name'          => $name,
            'description'   => $name,
            'price'         => [['from' => 0, 'to' => 'UNLIMITED', 'price' => 0]],
            'recurrence'    => ['frequency' => 1, 'start' => 1],
            'prorated'      => true,
            'include'       => $include,
            'from'          => new \Mongodloid_Date(strtotime('2024-01-01')),
            'to'            => new \Mongodloid_Date(strtotime(\Models_Entity::UNLIMITED_DATE)),
            'creation_time' => new \Mongodloid_Date(strtotime('2024-01-01')),
        ], $override);
        \Billrun_Factory::db()->servicesCollection()->insert($service);
        return $name;
    }

    protected function getService(string $name): array
    {
        $revisions = $this->getServiceRevisions($name);
        \PHPUnit\Framework\Assert::assertCount(1, $revisions, "service $name must have a single revision");
        return $revisions[0];
    }

    protected function getServiceRevisions(string $name): array
    {
        $cursor = \Billrun_Factory::db()->servicesCollection()->query(['name' => $name])->cursor()
            ->setReadPreference('RP_PRIMARY')->sort(['from' => 1]);
        $revisions = [];
        foreach ($cursor as $revision) {
            $revisions[] = $revision->getRawData();
        }
        return $revisions;
    }

    protected function getProductsGroups(string $key): array
    {
        $cursor = \Billrun_Factory::db()->productgroupsCollection()->query(['key' => $key])->cursor()
            ->setReadPreference('RP_PRIMARY');
        $productsGroups = [];
        foreach ($cursor as $productsGroup) {
            $productsGroups[] = $productsGroup->getRawData();
        }
        return $productsGroups;
    }
}
