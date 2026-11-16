<?php

/**
 * BRCD-5431 - customer pricing of services that include products groups
 * (include.products_groups) instead of the legacy include.groups.
 *
 * A service entry references a products group by key and holds either a usage
 * based quota (value + unit) or a monetary based quota (cost), plus the sharing
 * flags (account_shared / account_pool / quantity_affected). The products are
 * resolved from the products group revision that is active at the line time,
 * and the balance holds the products group key as its group name.
 *
 * Products groups, services, plans and rates are created through billapi (as the
 * UI does), lines are inserted directly and priced in-process by the
 * customerPricing calculator (updateRow -> writeLine -> removeBalanceTx).
 *
 * All rates are priced 1 per second, so a line aprice equals its charged volume.
 *
 * @package         Tests
 * @copyright       Copyright (C) 2012-2026 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */
class productsGroupsPricingCest
{
    protected $epsilon = 0.000001;
    protected $planName;

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
        // cleanDB() does not wipe the products groups, and an identical products
        // group (same products & activity types) is rejected on create
        \Billrun_Factory::db()->productgroupsCollection()->remove([null]);
        $this->planName = $this->generatePlan($I);
    }

    // =========================================================================
    // TESTS
    // =========================================================================

    /**
     * Usage based quota: the included volume is consumed first, the rest is
     * charged, and the service balance accumulates the usage under the products
     * group key. A following line finds the quota exhausted.
     */
    public function testUsageBasedQuota(ApiTester $I): void
    {
        $rate = $I->generateRate([], 'call');
        $group = $this->generateProductsGroup($I, [$rate], ['call']);
        $service = $I->generateServiceWithProductsGroups([
            ['key' => $group, 'value' => 60, 'unit' => 'seconds'],
        ]);

        $line = $this->priceLine($I, ['rate' => $rate, 'usagev' => 100, 'services' => [$service]]);
        $this->assertPricing($I, $line, 60, 40, 'partially covered line');

        $balance = $this->getBalance((int) $line['aid'], (int) $line['sid'], $service, $line);
        $I->assertArrayHasKey($group, $balance['balance']['groups'] ?? [], 'the balance group name must be the products group key');
        $this->assertDelta($I, 60, $balance['balance']['groups'][$group]['usagev'] ?? null, 'balance group usagev');
        $this->assertDelta($I, 0, $balance['balance']['groups'][$group]['left'] ?? null, 'balance group left');

        $line = $this->priceLine($I, ['rate' => $rate, 'usagev' => 30, 'services' => [$service]]);
        $this->assertPricing($I, $line, 0, 30, 'line after the quota was exhausted');
    }

    /**
     * Usage based quota of UNLIMITED covers any volume.
     */
    public function testUnlimitedUsageQuota(ApiTester $I): void
    {
        $rate = $I->generateRate([], 'call');
        $group = $this->generateProductsGroup($I, [$rate], ['call']);
        $service = $I->generateServiceWithProductsGroups([
            ['key' => $group, 'value' => \Billrun_Service::UNLIMITED_VALUE, 'unit' => 'seconds'],
        ]);

        $line = $this->priceLine($I, ['rate' => $rate, 'usagev' => 100000, 'services' => [$service]]);
        $this->assertPricing($I, $line, 100000, 0, 'unlimited quota');
    }

    /**
     * Monetary based quota on a products group without activity types: the
     * quota is not bound to a usage type, the cost is converted to the covered
     * volume, and the balance accumulates cost under the products group key.
     */
    public function testMonetaryQuotaOnUntypedGroup(ApiTester $I): void
    {
        $rate = $I->generateRate([], 'call');
        $group = $this->generateProductsGroup($I, [$rate], []);
        $service = $I->generateServiceWithProductsGroups([
            ['key' => $group, 'cost' => 10],
        ]);

        $line = $this->priceLine($I, ['rate' => $rate, 'usagev' => 30, 'services' => [$service]]);
        $this->assertPricing($I, $line, 10, 20, 'monetary quota of 10 at price 1 per second');

        $balance = $this->getBalance((int) $line['aid'], (int) $line['sid'], $service, $line);
        $this->assertDelta($I, 10, $balance['balance']['groups'][$group]['cost'] ?? null, 'balance group cost');
    }

    /**
     * A usage based quota covers only the activity types of its products group -
     * the same product used with another activity type is fully charged.
     */
    public function testUsageTypeNotInGroupIsNotIncluded(ApiTester $I): void
    {
        $rate = $I->generateRate([], ['call', 'data']);
        $group = $this->generateProductsGroup($I, [$rate], ['call']);
        $service = $I->generateServiceWithProductsGroups([
            ['key' => $group, 'value' => 60, 'unit' => 'seconds'],
        ]);

        $line = $this->priceLine($I, ['rate' => $rate, 'usaget' => 'data', 'usagev' => 50, 'services' => [$service]]);
        $this->assertPricing($I, $line, 0, 50, 'data usage on a call products group');
    }

    /**
     * A product that is not one of the products group products is fully charged.
     */
    public function testRateNotInGroupIsNotIncluded(ApiTester $I): void
    {
        $includedRate = $I->generateRate([], 'call');
        $otherRate = $I->generateRate([], 'call');
        $group = $this->generateProductsGroup($I, [$includedRate], ['call']);
        $service = $I->generateServiceWithProductsGroups([
            ['key' => $group, 'value' => 60, 'unit' => 'seconds'],
        ]);

        $line = $this->priceLine($I, ['rate' => $otherRate, 'usagev' => 50, 'services' => [$service]]);
        $this->assertPricing($I, $line, 0, 50, 'product outside the products group');
    }

    /**
     * BRCD-5431 test case - a product that exists in two different products
     * groups of the same service consumes both quotas, by the entries order,
     * each one accumulated on its own balance group.
     */
    public function testProductInTwoProductsGroupsOfSameService(ApiTester $I): void
    {
        $rate = $I->generateRate([], 'call');
        // the groups hold different products, as an identical products group is rejected
        $firstGroup = $this->generateProductsGroup($I, [$rate, $I->generateRate([], 'call')], ['call']);
        $secondGroup = $this->generateProductsGroup($I, [$rate, $I->generateRate([], 'call')], ['call']);
        $service = $I->generateServiceWithProductsGroups([
            ['key' => $firstGroup, 'value' => 30, 'unit' => 'seconds'],
            ['key' => $secondGroup, 'value' => 50, 'unit' => 'seconds'],
        ]);

        $line = $this->priceLine($I, ['rate' => $rate, 'usagev' => 100, 'services' => [$service]]);
        $this->assertPricing($I, $line, 80, 20, 'product in two products groups');

        $balance = $this->getBalance((int) $line['aid'], (int) $line['sid'], $service, $line);
        $this->assertDelta($I, 30, $balance['balance']['groups'][$firstGroup]['usagev'] ?? null, 'first products group usagev');
        $this->assertDelta($I, 50, $balance['balance']['groups'][$secondGroup]['usagev'] ?? null, 'second products group usagev');
    }

    /**
     * The products are taken from the products group revision active at the line
     * time: the product of the old revision is included only before the new
     * revision starts, and the product of the new revision only after.
     */
    public function testProductsGroupRevisionByLineTime(ApiTester $I): void
    {
        $oldRate = $I->generateRate([], 'call');
        $newRate = $I->generateRate([], 'call');
        // both lines must fall in the current billing cycle, so the revision
        // switch is set in the middle of the cycle part that already passed
        $cycleStart = \Billrun_Billingcycle::getStartTime(\Billrun_Billingcycle::getBillrunKeyByTimestamp(time()));
        $switchTime = (int) (($cycleStart + time()) / 2);
        $beforeSwitch = (int) (($cycleStart + $switchTime) / 2);
        $afterSwitch = time() - 1;

        $group = $I->uniqueEntityKey('PG');
        $productgroups = \Billrun_Factory::db()->productgroupsCollection();
        $oldRevision = [
            'key'           => $group,
            'description'   => 'old revision',
            'usage_types'   => ['call'],
            'property_type' => 'time',
            'rates'         => [$oldRate],
            'from'          => new \Mongodloid_Date(strtotime('2024-01-01')),
            'to'            => new \Mongodloid_Date($switchTime),
        ];
        $productgroups->insert($oldRevision);
        $newRevision = [
            'key'           => $group,
            'description'   => 'new revision',
            'usage_types'   => ['call'],
            'property_type' => 'time',
            'rates'         => [$newRate],
            'from'          => new \Mongodloid_Date($switchTime),
            'to'            => new \Mongodloid_Date(strtotime(\Models_Entity::UNLIMITED_DATE)),
        ];
        $productgroups->insert($newRevision);
        $service = $I->generateServiceWithProductsGroups([
            ['key' => $group, 'value' => 1000, 'unit' => 'seconds'],
        ]);

        $line = $this->priceLine($I, ['rate' => $oldRate, 'usagev' => 10, 'urt' => $beforeSwitch, 'services' => [$service]]);
        $this->assertPricing($I, $line, 10, 0, 'old revision product before the switch');
        $line = $this->priceLine($I, ['rate' => $newRate, 'usagev' => 10, 'urt' => $beforeSwitch, 'services' => [$service]]);
        $this->assertPricing($I, $line, 0, 10, 'new revision product before the switch');
        $line = $this->priceLine($I, ['rate' => $oldRate, 'usagev' => 10, 'urt' => $afterSwitch, 'services' => [$service]]);
        $this->assertPricing($I, $line, 0, 10, 'old revision product after the switch');
        $line = $this->priceLine($I, ['rate' => $newRate, 'usagev' => 10, 'urt' => $afterSwitch, 'services' => [$service]]);
        $this->assertPricing($I, $line, 10, 0, 'new revision product after the switch');
    }

    /**
     * account_shared: the quota is shared by all the account subscribers on a
     * single account level balance (sid 0).
     */
    public function testAccountSharedQuota(ApiTester $I): void
    {
        $rate = $I->generateRate([], 'call');
        $group = $this->generateProductsGroup($I, [$rate], ['call']);
        $service = $I->generateServiceWithProductsGroups([
            ['key' => $group, 'value' => 60, 'unit' => 'seconds', 'account_shared' => true],
        ]);
        [$aid, $sids] = $this->generateAccountWithSubscribers($I, 2, $service);

        $line = $this->priceLine($I, ['aid' => $aid, 'sid' => $sids[0], 'rate' => $rate, 'usagev' => 40, 'services' => [$service]]);
        $this->assertPricing($I, $line, 40, 0, 'first subscriber');
        $line = $this->priceLine($I, ['aid' => $aid, 'sid' => $sids[1], 'rate' => $rate, 'usagev' => 40, 'services' => [$service]]);
        $this->assertPricing($I, $line, 20, 20, 'second subscriber uses the shared leftover');

        // the shared usage accrues on the account level (sid 0) balance, not on a service balance
        $balance = $this->getBalance($aid, 0, null, $line);
        $I->assertNotEmpty($balance, 'an account level (sid 0) balance must hold the shared quota');
        $this->assertDelta($I, 60, $balance['balance']['groups'][$group]['usagev'] ?? null, 'shared balance group usagev');
    }

    /**
     * account_pool: the quota is multiplied by the number of the account
     * subscribers that have the service, and shared by them.
     */
    public function testAccountPoolQuota(ApiTester $I): void
    {
        $rate = $I->generateRate([], 'call');
        $group = $this->generateProductsGroup($I, [$rate], ['call']);
        $service = $I->generateServiceWithProductsGroups([
            ['key' => $group, 'value' => 30, 'unit' => 'seconds', 'account_shared' => true, 'account_pool' => true],
        ]);
        [$aid, $sids] = $this->generateAccountWithSubscribers($I, 2, $service);

        $line = $this->priceLine($I, ['aid' => $aid, 'sid' => $sids[0], 'rate' => $rate, 'usagev' => 50, 'services' => [$service]]);
        $this->assertPricing($I, $line, 50, 0, 'pool of 2 subscribers x 30');
        $line = $this->priceLine($I, ['aid' => $aid, 'sid' => $sids[1], 'rate' => $rate, 'usagev' => 20, 'services' => [$service]]);
        $this->assertPricing($I, $line, 10, 10, 'pool leftover');
    }

    /**
     * quantity_affected: the quota is multiplied by the service quantity.
     */
    public function testQuantityAffectedQuota(ApiTester $I): void
    {
        $rate = $I->generateRate([], 'call');
        $group = $this->generateProductsGroup($I, [$rate], ['call']);
        $service = $I->generateServiceWithProductsGroups([
            ['key' => $group, 'value' => 10, 'unit' => 'seconds', 'quantity_affected' => true],
        ]);

        $line = $this->priceLine($I, ['rate' => $rate, 'usagev' => 40, 'services' => [['name' => $service, 'quantity' => 3]]]);
        $this->assertPricing($I, $line, 30, 10, 'quota of 10 x quantity 3');
    }

    /**
     * Without quantity_affected the service quantity does not change the quota.
     */
    public function testQuantityNotAffectedQuota(ApiTester $I): void
    {
        $rate = $I->generateRate([], 'call');
        $group = $this->generateProductsGroup($I, [$rate], ['call']);
        $service = $I->generateServiceWithProductsGroups([
            ['key' => $group, 'value' => 10, 'unit' => 'seconds', 'quantity_affected' => false],
        ]);

        $line = $this->priceLine($I, ['rate' => $rate, 'usagev' => 40, 'services' => [['name' => $service, 'quantity' => 3]]]);
        $this->assertPricing($I, $line, 10, 30, 'quota of 10 regardless of quantity 3');
    }

    /**
     * On a products groups service the include.groups hold only counter groups:
     * the counters still count the charged usage, while the allowance comes from
     * the products groups.
     */
    public function testCounterGroupsOnProductsGroupsService(ApiTester $I): void
    {
        $rate = $I->generateRate([], 'call');
        $group = $this->generateProductsGroup($I, [$rate], ['call']);
        $counter = $I->uniqueEntityKey('COUNTER');
        $service = $this->insertService($I, [
            'products_groups' => [
                ['key' => $group, 'value' => 60, 'unit' => 'seconds', 'account_shared' => false, 'account_pool' => false, 'quantity_affected' => false],
            ],
            'groups' => [
                $counter => [
                    'counter_only' => true,
                    'rates'        => [$rate],
                    'usage_types'  => ['call' => ['unit' => 'seconds']],
                    'value'        => 0,
                ],
            ],
        ]);

        $line = $this->priceLine($I, ['rate' => $rate, 'usagev' => 100, 'services' => [$service]]);
        $this->assertPricing($I, $line, 60, 40, 'products group allowance');
        $this->assertDelta($I, 40, $line['counter_group'] ?? null, 'the counter group must count the charged part');
    }

    /**
     * On a products groups service, a non counter group left in include.groups is
     * not an allowance anymore - only the products groups are.
     */
    public function testLegacyGroupsIgnoredOnProductsGroupsService(ApiTester $I): void
    {
        $rate = $I->generateRate([], 'call');
        $group = $this->generateProductsGroup($I, [$rate], ['call']);
        $service = $this->insertService($I, [
            'products_groups' => [
                ['key' => $group, 'value' => 20, 'unit' => 'seconds', 'account_shared' => false, 'account_pool' => false, 'quantity_affected' => false],
            ],
            'groups' => [
                $I->uniqueEntityKey('LEGACY') => [
                    'rates'       => [$rate],
                    'usage_types' => ['call' => ['unit' => 'seconds']],
                    'value'       => 1000,
                ],
            ],
        ]);

        $line = $this->priceLine($I, ['rate' => $rate, 'usagev' => 100, 'services' => [$service]]);
        $this->assertPricing($I, $line, 20, 80, 'only the products group allowance applies');
    }

    /**
     * Legacy services (include.groups only) keep working - b/c.
     */
    public function testLegacyGroupsServiceBackwardCompatibility(ApiTester $I): void
    {
        $rate = $I->generateRate([], 'call');
        $legacyGroup = $I->uniqueEntityKey('LEGACY');
        $service = $this->insertService($I, [
            'groups' => [
                $legacyGroup => [
                    'rates'             => [$rate],
                    'usage_types'       => ['call' => ['unit' => 'seconds']],
                    'value'             => 60,
                    'account_shared'    => false,
                    'account_pool'      => false,
                    'quantity_affected' => false,
                ],
            ],
        ]);

        $line = $this->priceLine($I, ['rate' => $rate, 'usagev' => 100, 'services' => [$service]]);
        $this->assertPricing($I, $line, 60, 40, 'legacy include.groups service');
        $balance = $this->getBalance((int) $line['aid'], (int) $line['sid'], $service, $line);
        $this->assertDelta($I, 60, $balance['balance']['groups'][$legacyGroup]['usagev'] ?? null, 'legacy balance group usagev');
    }

    /**
     * BRCD-5431 migration - a legacy group migrated to a products group of the same
     * key keeps consuming the balance the legacy group accumulated before the
     * migration, and the migrated service prices the same way.
     */
    public function testLegacyBalanceContinuesAfterMigration(ApiTester $I): void
    {
        $rate = $I->generateRate([], 'call');
        $legacyGroup = $I->uniqueEntityKey('LEGACY');
        $service = $this->insertService($I, [
            'groups' => [
                $legacyGroup => [
                    'rates'       => [$rate],
                    'usage_types' => ['call' => ['unit' => 'seconds']],
                    'value'       => 60,
                ],
            ],
        ]);

        $line = $this->priceLine($I, ['rate' => $rate, 'usagev' => 40, 'services' => [$service]]);
        $this->assertPricing($I, $line, 40, 0, 'legacy group before the migration');

        $migration = require APPLICATION_PATH . '/application/migrations/20260903_001_BRCD-5431.php';
        $config = [];
        $migration->setContext(\Billrun_Factory::db(), $config);
        $migration->run();
        $include = \Billrun_Factory::db()->servicesCollection()->query(['name' => $service])->cursor()
            ->setReadPreference('RP_PRIMARY')->current()->get('include');
        $I->assertArrayNotHasKey('groups', $include, 'the service must be migrated');

        $line = $this->priceLine($I, ['rate' => $rate, 'usagev' => 40, 'services' => [$service]]);
        $this->assertPricing($I, $line, 20, 20, 'migrated products group continues the legacy balance');
        $balance = $this->getBalance((int) $line['aid'], (int) $line['sid'], $service, $line);
        $this->assertDelta($I, 60, $balance['balance']['groups'][$legacyGroup]['usagev'] ?? null, 'balance group usagev');
    }

    /**
     * A legacy service and a products groups service on the same line: both
     * allowances are consumed.
     */
    public function testLegacyAndProductsGroupsServicesTogether(ApiTester $I): void
    {
        $rate = $I->generateRate([], 'call');
        $group = $this->generateProductsGroup($I, [$rate], ['call']);
        $productsGroupsService = $I->generateServiceWithProductsGroups([
            ['key' => $group, 'value' => 30, 'unit' => 'seconds'],
        ]);
        $legacyService = $this->insertService($I, [
            'groups' => [
                $I->uniqueEntityKey('LEGACY') => [
                    'rates'       => [$rate],
                    'usage_types' => ['call' => ['unit' => 'seconds']],
                    'value'       => 20,
                ],
            ],
        ]);

        $line = $this->priceLine($I, ['rate' => $rate, 'usagev' => 100, 'services' => [$productsGroupsService, $legacyService]]);
        $this->assertPricing($I, $line, 50, 50, 'legacy 20 + products group 30');
    }

    /**
     * The plan includes (legacy include.groups) are consumed before the
     * products groups of the services.
     */
    public function testPlanIncludesBeforeProductsGroups(ApiTester $I): void
    {
        $rate = $I->generateRate([], 'call');
        $planGroup = $I->uniqueEntityKey('PLANGROUP');
        $this->planName = $this->generatePlan($I, [
            'include' => [
                'groups' => [
                    $planGroup => [
                        'rates'             => [$rate],
                        'usage_types'       => ['call' => ['unit' => 'seconds']],
                        'value'             => 20,
                        'account_shared'    => false,
                        'account_pool'      => false,
                        'quantity_affected' => false,
                    ],
                ],
            ],
        ]);
        $group = $this->generateProductsGroup($I, [$rate], ['call']);
        $service = $I->generateServiceWithProductsGroups([
            ['key' => $group, 'value' => 30, 'unit' => 'seconds'],
        ]);

        $line = $this->priceLine($I, ['rate' => $rate, 'usagev' => 100, 'services' => [$service]]);
        $this->assertPricing($I, $line, 50, 50, 'plan 20 + products group 30');

        $planBalance = $this->getBalance((int) $line['aid'], (int) $line['sid'], null, $line);
        $this->assertDelta($I, 20, $planBalance['balance']['groups'][$planGroup]['usagev'] ?? null, 'plan balance group usagev');
        $serviceBalance = $this->getBalance((int) $line['aid'], (int) $line['sid'], $service, $line);
        $this->assertDelta($I, 30, $serviceBalance['balance']['groups'][$group]['usagev'] ?? null, 'service balance products group usagev');
    }

    /**
     * A service entry whose products group no longer exists is skipped - the
     * line is charged and pricing does not fail.
     */
    public function testMissingProductsGroupIsSkipped(ApiTester $I): void
    {
        $rate = $I->generateRate([], 'call');
        $group = $this->generateProductsGroup($I, [$rate], ['call']);
        $service = $I->generateServiceWithProductsGroups([
            ['key' => $group, 'value' => 60, 'unit' => 'seconds'],
        ]);
        \Billrun_Factory::db()->productgroupsCollection()->remove(['key' => $group]);

        $line = $this->priceLine($I, ['rate' => $rate, 'usagev' => 50, 'services' => [$service]]);
        $this->assertPricing($I, $line, 0, 50, 'products group removed');
    }

    /**
     * Billrun_Rates_Util::checkIfRateInclude resolves the products of a products
     * groups entity from the products group revision active at the given time.
     */
    public function testCheckIfRateIncludeWithProductsGroups(ApiTester $I): void
    {
        $includedRate = $I->generateRate([], 'call');
        $otherRate = $I->generateRate([], 'call');
        $group = $this->generateProductsGroup($I, [$includedRate], ['call']);
        $entity = ['include' => ['products_groups' => [['key' => $group, 'value' => 60, 'unit' => 'seconds']]]];
        $this->resetEntityCaches();

        $I->assertTrue(\Billrun_Rates_Util::checkIfRateInclude($includedRate, $entity, time()), 'a products group product is included');
        $I->assertFalse(\Billrun_Rates_Util::checkIfRateInclude($otherRate, $entity, time()), 'a product outside the products group is not included');
        $I->assertFalse(\Billrun_Rates_Util::checkIfRateInclude($includedRate, $entity, strtotime('2000-01-01')), 'no products group revision is active at that time');
    }

    // =========================================================================
    // CALCULATOR HELPERS
    // =========================================================================

    /**
     * Insert a line and price it with the customerPricing calculator.
     *
     * @param array $params rate, usagev, services (names or service_data arrays),
     *                      and optionally aid, sid, usaget, urt (unix time)
     * @return array the priced line
     */
    protected function priceLine(ApiTester $I, array $params): array
    {
        $aid = $params['aid'] ?? 9001;
        $sid = $params['sid'] ?? 9002;
        $urt = $params['urt'] ?? time();
        $servicesData = [];
        foreach ($params['services'] ?? [] as $index => $service) {
            $service = is_array($service) ? $service : ['name' => $service];
            $servicesData[] = array_merge([
                'from'       => new \Mongodloid_Date(strtotime('2025-01-01')),
                'to'         => new \Mongodloid_Date(strtotime('2126-01-01')),
                'service_id' => $index + 1,
                'quantity'   => 1,
            ], $service);
        }
        $rateRef = $this->getRateRef($params['rate']);
        $plan = \Billrun_Factory::db()->plansCollection()->query(['name' => $this->planName])->cursor()->current();
        $line = [
            'stamp'         => md5(uniqid('pg', true)),
            'type'          => 'productsGroupsTest',
            'aid'           => $aid,
            'sid'           => $sid,
            'usaget'        => $params['usaget'] ?? 'call',
            'usagev'        => $params['usagev'],
            'urt'           => new \Mongodloid_Date($urt),
            'process_time'  => new \Mongodloid_Date(time() - 200),
            'arate'         => $rateRef,
            'arate_key'     => $params['rate'],
            'rates'         => [['rate' => $rateRef, 'tariff_category' => 'retail']],
            'plan'          => $this->planName,
            'plan_ref'      => \Mongodloid_Ref::create('plans', new \Mongodloid_Id((string) $plan['_id'])),
            'services_data' => $servicesData,
        ];
        $linesCol = \Billrun_Factory::db()->linesCollection();
        $linesCol->insert($line, ['w' => 'majority']);

        $this->resetEntityCaches();
        $I->resetBillrunSingletons();
        $calculator = \Billrun_Calculator::getInstance(['type' => 'customerPricing', 'autoload' => false]);
        $entity = $linesCol->setReadPreference('RP_PRIMARY')->query(['stamp' => $line['stamp']])->cursor()->current();
        $calculator->updateRow($entity);
        $calculator->writeLine($entity, '123');
        $calculator->removeBalanceTx($entity);
        return $entity->getRawData();
    }

    /**
     * The entities are cached process-wide (services, plans and products groups
     * are loaded once), while the tests create them through billapi in the web
     * process - drop the caches so the calculator reads the current DB state.
     */
    protected function resetEntityCaches(): void
    {
        $caches = [
            ['Billrun_Service', 'entities', []],
            ['Billrun_Service', 'cache', []],
            ['Billrun_Service', 'serviceMaximumQuantityByAid', []],
            ['Billrun_Plan', 'cache', []],
            ['Billrun_Productgroups', 'entities', []],
            ['Billrun_Factory', 'plan', []],
            ['Billrun_Factory', 'service', []],
            ['Billrun_Factory', 'productgroup', []],
            ['Billrun_Rates_Util', 'ratesCache', []],
        ];
        foreach ($caches as [$class, $property, $value]) {
            $reflection = new \ReflectionProperty($class, $property);
            $reflection->setAccessible(true);
            $reflection->setValue(null, $value);
        }
    }

    /**
     * Assert the line in/over group split and its charge (price 1 per second).
     */
    protected function assertPricing(ApiTester $I, array $line, float $inGroup, float $overGroup, string $context): void
    {
        $I->assertArrayHasKey('aprice', $line, "[$context] the line must be priced");
        $this->assertDelta($I, $inGroup, $line['in_group'] ?? 0, "[$context] in_group");
        $this->assertDelta($I, $overGroup, $line['over_group'] ?? $line['out_group'] ?? 0, "[$context] over_group/out_group");
        $this->assertDelta($I, $overGroup, $line['aprice'], "[$context] aprice");
    }

    protected function assertDelta(ApiTester $I, $expected, $actual, string $message): void
    {
        \PHPUnit\Framework\Assert::assertNotNull($actual, $message . ' is missing');
        \PHPUnit\Framework\Assert::assertEqualsWithDelta($expected, $actual, $this->epsilon, $message);
    }

    /**
     * Fetch the balance of the line billing cycle: the main balance when
     * $serviceName is null, else the balance of that service.
     */
    protected function getBalance(int $aid, int $sid, ?string $serviceName, array $line): array
    {
        $billrunKey = \Billrun_Billingcycle::getBillrunKeyByTimestamp($line['urt']->sec);
        $entity = \Billrun_Factory::db()->balancesCollection()
            ->query([
                'aid'          => $aid,
                'sid'          => $sid,
                'from'         => ['$lte' => new \Mongodloid_Date($line['urt']->sec)],
                'to'           => ['$gt' => new \Mongodloid_Date($line['urt']->sec)],
                'service_name' => is_null($serviceName) ? ['$exists' => false] : $serviceName,
            ])
            ->cursor()->current();
        return $entity->isEmpty() ? [] : $entity->getRawData();
    }

    protected function getRateRef(string $key)
    {
        $rate = \Billrun_Factory::db()->ratesCollection()->setReadPreference('RP_PRIMARY')
            ->query(['key' => $key])->cursor()->current();
        \PHPUnit\Framework\Assert::assertFalse($rate->isEmpty(), "rate $key must exist");
        return \Mongodloid_Ref::create('rates', new \Mongodloid_Id((string) $rate['_id']));
    }

    // =========================================================================
    // DATA HELPERS
    // =========================================================================

    /**
     * Create a products group through billapi.
     *
     * @return string the products group key
     */
    protected function generateProductsGroup(ApiTester $I, array $rates, array $usageTypes): string
    {
        $key = $I->uniqueEntityKey('PG');
        $I->generateProductsGroup([
            'key'         => $key,
            'from'        => '2024-01-01',
            'usage_types' => $usageTypes,
            'rates'       => $rates,
        ]);
        $I->seeResponseContains('"status":1');
        return $key;
    }

    /**
     * Create a plan through billapi.
     *
     * @return string the plan name
     */
    protected function generatePlan(ApiTester $I, array $override = []): string
    {
        $name = $I->uniqueEntityKey('PG_PLAN');
        $I->generatePlan(array_merge(['name' => $name, 'from' => '2024-01-01'], $override));
        $I->seeResponseContains('"status":1');
        return $name;
    }

    /**
     * Insert a service directly to the DB, for include structures billapi does not
     * create (legacy include.groups, or include.groups next to products groups).
     *
     * @return string the service name
     */
    protected function insertService(ApiTester $I, array $include): string
    {
        $name = $I->uniqueEntityKey('SERVICE');
        $service = [
            'name'          => $name,
            'description'   => $name,
            'price'         => [['from' => 0, 'to' => 'UNLIMITED', 'price' => 0]],
            'recurrence'    => ['frequency' => 1, 'start' => 1],
            'prorated'      => true,
            'include'       => $include,
            'from'          => new \Mongodloid_Date(strtotime('2024-01-01')),
            'to'            => new \Mongodloid_Date(strtotime(\Models_Entity::UNLIMITED_DATE)),
            'creation_time' => new \Mongodloid_Date(strtotime('2024-01-01')),
        ];
        \Billrun_Factory::db()->servicesCollection()->insert($service);
        return $name;
    }

    /**
     * Create an account with subscribers that all have the service.
     *
     * @return array [aid, sids]
     */
    protected function generateAccountWithSubscribers(ApiTester $I, int $count, string $serviceName): array
    {
        $I->createAccountWithAllMandatoryCustomFields(['firstname' => 'products-groups-acc']);
        $aid = (int) json_decode($I->grabResponse(), true)['entity']['aid'];
        $sids = [];
        for ($i = 0; $i < $count; $i++) {
            $I->generateSubscriber([
                'from'      => '2025-01-01',
                'aid'       => $aid,
                'plan'      => $this->planName,
                'firstname' => 'pg-sub-' . $i,
                'services'  => [['name' => $serviceName, 'from' => '2025-01-01', 'to' => '2126-01-01']],
            ]);
            $sids[] = (int) json_decode($I->grabResponse(), true)['entity']['sid'];
        }
        return [$aid, $sids];
    }
}
