<?php

/**
 * UpdateRow test - Codeception port of the (removed) legacy library/Tests/Updaterowt.php.
 *
 * Prices lines with the customerPricing calculator (updateRow -> writeLine ->
 * removeBalanceTx) and verifies the in/over group split, the charge, the billrun
 * key, and - for the cases that ask for it - that a rebalance (ResetLinesModel)
 * resets the account balances.
 *
 * Each case of tests/_data/updaterow/cases.php is one example of the updateRow
 * test, and the examples run one after the other on the same DB state, as the
 * legacy test ran them: the fixtures (tests/_data/updaterow/fixtures) are loaded
 * once before the first case, and a case relies on the balances the previous
 * cases of its subscriber left. So a case must not be run alone (e.g. by a
 * filter) unless it is the first case of its subscriber.
 *
 * The test replaces the tenant config and the entity collections with the
 * fixtures, so it backs them up before the first case and restores them after
 * the last one.
 *
 * @package         Tests
 * @copyright       Copyright (C) 2012-2026 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */
class updateRowCest
{
    const DATA_PATH = APPLICATION_PATH . '/tests/_data/updaterow/';

    /**
     * collections the fixtures replace, and the fixture file (if any) loaded to each
     */
    const COLLECTIONS = [
        'plans'           => 'plans.json',
        'services'        => 'services.json',
        'subscribers'     => 'subscribers.json',
        'rates'           => 'rates.json',
        'taxes'           => 'taxes.json',
        'lines'           => null,
        'balances'        => null,
        'rebalance_queue' => null,
        'billrun'         => null,
        'billing_cycle'   => null,
    ];

    /**
     * the legacy CI run (apiBCCest::updateRowT) skipped these cases - their
     * expected billrun depends on the day of the month the test runs on
     * (urt and invoicing day relative to today)
     */
    const SKIPPED = [
        'z5' => 'depends on the run day of the month (urt Y-m-10, invoicing day 28) - skipped in the legacy CI run as well',
        'z7' => 'depends on the run day of the month (urt Y-m-28 of last month, invoicing day 22) - skipped in the legacy CI run as well',
    ];

    protected $epsilon = 0.000001;

    /**
     * @var array the cases, evaluated after the fixtures config was loaded
     */
    protected static $cases;
    protected static $calculator;
    protected static $backup;
    protected static $currentIndex;

    // =========================================================================
    // LIFECYCLE
    // =========================================================================

    public function _after(ApiTester $I)
    {
        if (!is_null(self::$backup) && self::$currentIndex === count(self::$cases) - 1) {
            $this->tearDownFixtures();
        }
    }

    /**
     * The cases, as examples named by their stamp. The case values are evaluated
     * again once the fixtures are loaded (some depend on the tenant config, e.g.
     * the billrun key of the previous month), here only their stamps are used.
     */
    protected function casesProvider(): array
    {
        $examples = [];
        foreach (require self::DATA_PATH . 'cases.php' as $index => $case) {
            $examples[$case['row']['stamp']] = ['index' => $index, 'stamp' => $case['row']['stamp']];
        }
        return $examples;
    }

    // =========================================================================
    // TEST
    // =========================================================================

    /**
     * @dataProvider casesProvider
     */
    public function updateRow(ApiTester $I, \Codeception\Example $example): void
    {
        if (is_null(self::$backup)) {
            $this->setUpFixtures($I);
        }
        self::$currentIndex = $example['index'];
        $case = self::$cases[$example['index']];
        $stamp = $case['row']['stamp'];
        $I->assertEquals($example['stamp'], $stamp, 'the cases must be in the order of the provider');
        if (isset(self::SKIPPED[$stamp])) {
            \PHPUnit\Framework\Assert::markTestSkipped(self::SKIPPED[$stamp]);
        }

        foreach ($case['preTest'] ?? [] as $function) {
            $this->$function($case);
        }
        $row = $this->fixRow($case['row'], $example['index']);
        \Billrun_Factory::db()->linesCollection()->insert($row, ['w' => 'majority']);
        $updatedRow = $this->runT($row['stamp']);
        $this->compareExpected($I, $updatedRow, $case);
        if (!empty($case['rebalance'])) {
            $this->rebalance($I, $updatedRow, $case);
        }
    }

    // =========================================================================
    // FIXTURES
    // =========================================================================

    /**
     * Back up the tenant config and the collections, load the fixtures, and
     * evaluate the cases with the fixtures config.
     */
    protected function setUpFixtures(ApiTester $I): void
    {
        $billrunConfig = \Billrun_Config::getInstance();
        $tenantConfig = \Billrun_Factory::db()->configCollection()->query()->cursor()->setReadPreference('RP_PRIMARY')
            ->sort(['urt' => -1, '_id' => -1])->limit(1)->current();
        self::$backup = [
            'timezone'     => date_default_timezone_get(),
            'config'       => $this->getProperty($billrunConfig, 'config'),
            'loadedFiles'  => $this->getProperty($billrunConfig, 'loadedFiles'),
            'tenantConfig' => $tenantConfig->isEmpty() ? null : $tenantConfig->getRawData(),
            'collections'  => [],
        ];
        foreach (array_keys(self::COLLECTIONS) as $collection) {
            self::$backup['collections'][$collection] = [];
            foreach (\Billrun_Factory::db()->{$collection . 'Collection'}()->query()->cursor() as $document) {
                self::$backup['collections'][$collection][] = $document->getRawData();
            }
        }

        date_default_timezone_set('Asia/Jerusalem');
        foreach (self::COLLECTIONS as $collection => $file) {
            \Billrun_Factory::db()->{$collection . 'Collection'}()->remove([null]);
            if (!is_null($file)) {
                $this->loadFixture($collection, $file);
            }
        }
        // the fixtures config is inserted as the latest config - the config collection is
        // capped (no removes), so on tear down the tenant config is inserted back on top of it
        $this->loadFixture('config', 'config.json');
        $billrunConfig->loadDbConfig();

        $this->resetCaches($I);
        self::$cases = require self::DATA_PATH . 'cases.php';
        self::$calculator = \Billrun_Calculator::getInstance(['type' => 'customerPricing', 'autoload' => false]);
    }

    /**
     * Restore the tenant config, the collections and the in-memory config. The
     * tenant config goes first, and the in-memory state is restored even if a
     * DB restore fails, so a failure here does not break the tests that follow.
     */
    protected function tearDownFixtures(): void
    {
        try {
            // the config collection is capped, so the tenant config is inserted back as the
            // latest config (like the legacy test did) instead of removing the fixtures config
            if (!is_null(self::$backup['tenantConfig'])) {
                $tenantConfig = self::$backup['tenantConfig'];
                unset($tenantConfig['_id']);
                $tenantConfig['urt'] = new \MongoDB\BSON\UTCDateTime();
                \Billrun_Factory::db()->configCollection()->insert($tenantConfig);
            }
            foreach (self::$backup['collections'] as $collection => $documents) {
                $coll = \Billrun_Factory::db()->{$collection . 'Collection'}();
                $coll->remove([null]);
                if (!empty($documents)) {
                    $coll->batchInsert($documents);
                }
            }
        } finally {
            $billrunConfig = \Billrun_Config::getInstance();
            $this->setProperty($billrunConfig, 'config', self::$backup['config']);
            $this->setProperty($billrunConfig, 'loadedFiles', self::$backup['loadedFiles']);
            date_default_timezone_set(self::$backup['timezone']);
            $this->tearDownState();
        }
    }

    protected function tearDownState(): void
    {
        self::$backup = null;
        self::$cases = null;
        self::$calculator = null;
    }

    protected function loadFixture(string $collection, string $file): void
    {
        $parsed = json_decode(file_get_contents(self::DATA_PATH . 'fixtures/' . $file), true);
        \PHPUnit\Framework\Assert::assertNotNull($parsed, "fixture $file must be valid json");
        if (empty($parsed['data'])) {
            return;
        }
        $data = [];
        foreach ($parsed['data'] as $document) {
            $document = $this->fixDates($document);
            if (isset($document['OBJID'])) {
                $document['_id'] = new \Mongodloid_Id($document['OBJID']);
                unset($document['OBJID']);
            }
            $data[] = $document;
        }
        if ($collection === 'config') {
            $data[0]['urt'] = new \MongoDB\BSON\UTCDateTime();
        }
        \Billrun_Factory::db()->{$collection . 'Collection'}()->batchInsert($data);
    }

    /**
     * convert "time*<date>" strings to Mongodloid_Date
     */
    protected function fixDates($value)
    {
        if (is_string($value)) {
            $parts = explode('*', $value);
            if (count($parts) == 2 && $parts[0] == 'time') {
                return new \Mongodloid_Date(strtotime($parts[1]));
            }
            return $value;
        }
        if (is_array($value)) {
            foreach ($value as $field => $fieldValue) {
                $value[$field] = $this->fixDates($fieldValue);
            }
        }
        return $value;
    }

    /**
     * The entities and calculators are cached process-wide, and earlier tests of
     * the run may have cached other data - drop the caches so the cases use the
     * fixtures.
     */
    protected function resetCaches(ApiTester $I): void
    {
        $I->resetBillrunSingletons();
        $caches = [
            ['Billrun_Service', 'entities'],
            ['Billrun_Service', 'cache'],
            ['Billrun_Service', 'serviceMaximumQuantityByAid'],
            ['Billrun_Plan', 'cache'],
            ['Billrun_Productgroups', 'entities'],
            ['Billrun_Factory', 'plan'],
            ['Billrun_Factory', 'service'],
            ['Billrun_Factory', 'productgroup'],
            ['Billrun_Rates_Util', 'ratesCache'],
        ];
        foreach ($caches as [$class, $property]) {
            $reflection = new \ReflectionProperty($class, $property);
            $reflection->setAccessible(true);
            $reflection->setValue(null, []);
        }
    }

    protected function getProperty($object, string $property)
    {
        $reflection = new \ReflectionProperty($object, $property);
        $reflection->setAccessible(true);
        return $reflection->getValue($object);
    }

    protected function setProperty($object, string $property, $value): void
    {
        $reflection = new \ReflectionProperty($object, $property);
        $reflection->setAccessible(true);
        $reflection->setValue($object, $value);
    }

    // =========================================================================
    // PRICING
    // =========================================================================

    protected function runT(string $stamp): array
    {
        $entity = \Billrun_Factory::db()->linesCollection()->setReadPreference('RP_PRIMARY')
            ->query(['stamp' => $stamp])->cursor()->current();
        self::$calculator->updateRow($entity);
        self::$calculator->writeLine($entity, '123');
        self::$calculator->removeBalanceTx($entity);
        return $entity->getRawData();
    }

    /**
     * Complete the case row to a line: dates, default aid/sid/type/usaget, the
     * services data, and the rates and plan references.
     */
    protected function fixRow(array $row, int $index): array
    {
        if (!array_key_exists('process_time', $row)) {
            $row['process_time'] = new \Mongodloid_Date(time() - 200);
        } else {
            $row['process_time'] = new \Mongodloid_Date(strtotime($row['urt']) - 200);
        }
        if (!array_key_exists('urt', $row)) {
            $row['urt'] = new \Mongodloid_Date(time() + $index);
        } else {
            $row['urt'] = new \Mongodloid_Date(strtotime($row['urt']));
        }
        $row['aid'] = $row['aid'] ?? 1234;
        $row['sid'] = $row['sid'] ?? 1234;
        $row['type'] = $row['type'] ?? 'mytype';
        $row['usaget'] = $row['usaget'] ?? 'call';
        if (isset($row['services_data'])) {
            foreach ($row['services_data'] as $key => $service) {
                if (!is_array($service)) {
                    $row['services_data'][$key] = [
                        'name'       => $service,
                        'service_id' => $this->calcServiceId($row['aid'], $row['sid'], $service),
                    ];
                }
                foreach (['from', 'to', 'creation_time'] as $dateField) {
                    if (isset($service[$dateField])) {
                        $row['services_data'][$key][$dateField] = new \Mongodloid_Date(strtotime($service[$dateField]));
                    }
                }
            }
        }

        if (isset($row['arate_key'])) {
            $row['rates'] = [$row['arate_key'] => 'retail'];
        }
        $rates = [];
        foreach ($row['rates'] as $rateKey => $tariffCategory) {
            $rate = \Billrun_Factory::db()->ratesCollection()->setReadPreference('RP_PRIMARY')
                ->query(['key' => $rateKey])->cursor()->current();
            $rates[] = [
                'rate'            => \Mongodloid_Ref::create('rates', new \Mongodloid_Id((string) $rate['_id'])),
                'tariff_category' => $tariffCategory,
            ];
        }
        $row['rates'] = $rates;
        $plan = \Billrun_Factory::db()->plansCollection()->query(['name' => $row['plan']])->cursor()->current();
        $row['plan_ref'] = \Mongodloid_Ref::create('plans', new \Mongodloid_Id((string) $plan['_id']));
        return $row;
    }

    protected function calcServiceId($aid, $sid, $service)
    {
        $countChar = 0;
        foreach (str_split($service) as $char) {
            $countChar += ord($char);
        }
        return $aid * $sid * $countChar;
    }

    /**
     * Verify the priced line against the case expected values, with the legacy
     * test semantics:
     *  - aprice, in_group and (when given) out_group / billrun must match
     *  - over_group must match when set; an over_group expectation is also met by
     *    out_plan alone (a line out of the plan has no over_group)
     *  - for each expected charge tariff category the line must have a rate of that
     *    category, and the retail rate charge must equal aprice (the expected charge
     *    value itself is informational, e.g. it may include tax)
     */
    protected function compareExpected(ApiTester $I, array $line, array $case): void
    {
        $expected = $case['expected'];
        $stamp = $line['stamp'];
        $aprice = round(10 * $expected['aprice'], (1 / $this->epsilon)) / 10;

        if (isset($expected['billrun'])) {
            $I->assertEquals($expected['billrun'], $line['billrun'] ?? null, "[$stamp] billrun key");
        }

        $this->assertEqual($I, $aprice, $line['aprice'] ?? null, "[$stamp] aprice");

        foreach ($expected['charge'] ?? [] as $category => $price) {
            $categoryRate = current(array_filter($line['rates'], function (array $rate) use ($category) {
                return $rate['tariff_category'] === $category;
            }));
            $I->assertNotEmpty($categoryRate, "[$stamp] the line must have a $category rate");
            if ($categoryRate['tariff_category'] == 'retail') {
                $this->assertEqual($I, $aprice, $categoryRate['pricing']['charge'] ?? null, "[$stamp] $category charge must equal aprice");
            }
        }

        if ($expected['in_group'] == 0) {
            if (isset($line['in_group'])) {
                $this->assertEqual($I, 0, $line['in_group'], "[$stamp] in_group");
            }
        } else {
            $I->assertArrayHasKey('in_group', $line, "[$stamp] in_group must be set");
            $this->assertEqual($I, $expected['in_group'], $line['in_group'], "[$stamp] in_group");
        }

        if ($expected['over_group'] == 0) {
            if (isset($line['over_group'])) {
                $this->assertEqual($I, 0, $line['over_group'], "[$stamp] over_group");
                if (isset($line['out_plan'])) {
                    $this->assertEqual($I, 0, $line['out_plan'], "[$stamp] out_plan (with over_group set)");
                }
            }
        } else {
            $I->assertTrue(isset($line['over_group']) || isset($line['out_plan']), "[$stamp] over_group or out_plan must be set");
            if (isset($line['over_group'])) {
                $this->assertEqual($I, $expected['over_group'], $line['over_group'], "[$stamp] over_group");
            }
        }

        if (isset($expected['out_group'])) {
            $this->assertEqual($I, $expected['out_group'], $line['out_group'] ?? null, "[$stamp] out_group");
        }
    }

    protected function assertEqual(ApiTester $I, $expected, $actual, string $message): void
    {
        $I->assertNotNull($actual, "$message is missing");
        $I->assertTrue(\Billrun_Util::isEqual($actual, $expected, $this->epsilon), "$message: expected $expected, got $actual");
    }

    // =========================================================================
    // REBALANCE
    // =========================================================================

    /**
     * Rebalance the line account (optionally by conditions) and verify the
     * balances were reset.
     */
    protected function rebalance(ApiTester $I, array $line, array $case): void
    {
        $aids = [$line['aid']];
        $billrunKey = !empty($line['billrun']) ? $line['billrun'] : \Billrun_Billingcycle::getBillrunKeyByTimestamp($line['urt']->sec);
        \Billrun_Factory::config()->addConfig(self::DATA_PATH . 'conf/process_time_offset.ini');
        $conditions = $case['conditions'] ?? null;
        $stamps = [$line['stamp'] => $line['stamp']];
        (new \ResetLinesModel($aids, $billrunKey, $conditions, $stamps))->reset();

        $balances = [];
        foreach (\Billrun_Factory::db()->balancesCollection()->query(['aid' => $line['aid']])->cursor() as $balance) {
            $balances[] = $balance->getRawData();
        }
        if (isset($case['conditions'])) {
            $this->checkConditionalRebalance($I, $line['stamp'], $balances, $case['groupExpected']);
        } else {
            $this->checkRebalance($I, $line['stamp'], $balances, $case['row']['sid']);
        }
    }

    /**
     * All the usage and cost fields of the subscriber and the account (sid 0)
     * balances must be reset.
     */
    protected function checkRebalance(ApiTester $I, string $stamp, array $balances, $sid): void
    {
        foreach ($balances as $balance) {
            if ($balance['sid'] != 0 && $balance['sid'] != $sid) {
                continue;
            }
            $name = 'sid ' . $balance['sid'] . ' ' . ($balance['service_name'] ?? 'default') . ' balance';
            $this->assertBalanceReset($I, $balance, "[$stamp] rebalance: $name");
        }
    }

    protected function assertBalanceReset(ApiTester $I, array $values, string $path): void
    {
        foreach ($values as $key => $value) {
            if (is_array($value) && !empty($value)) {
                $this->assertBalanceReset($I, $value, "$path.$key");
            }
            if (in_array($key, ['cost', 'count', 'usagev', 'cost_with_tax'], true)) {
                $I->assertTrue(\Billrun_Util::isEqual($value, 0, \Billrun_Bill::precision), "$path.$key must be reset, got " . json_encode($value));
            }
        }
    }

    /**
     * Only the groups that met the conditions are reset: each service balance of
     * groupExpected must hold the expected values in its (first) expected group.
     */
    protected function checkConditionalRebalance(ApiTester $I, string $stamp, array $balances, array $groupExpected): void
    {
        foreach ($balances as $balance) {
            if (!isset($balance['service_name']) || !array_key_exists($balance['service_name'], $groupExpected)) {
                continue;
            }
            $expected = $groupExpected[$balance['service_name']];
            $group = key($expected);
            foreach ($balance['balance']['groups'][$group] as $field => $value) {
                $I->assertTrue($value == $expected[$group][$field], "[$stamp] conditional rebalance: {$balance['service_name']}.$group.$field expected {$expected[$group][$field]}, got $value");
            }
        }
    }

    // =========================================================================
    // PRE TEST HOOKS
    // =========================================================================

    protected function runCycle(array $case): void
    {
        $options = [
            'type'           => 'customer',
            'stamp'          => $case['row']['runCycle']['stamp'] ?? '201806',
            'page'           => 0,
            'size'           => 100,
            'fetchonly'      => true,
            'generate_pdf'   => 0,
            'force_accounts' => $case['row']['runCycle']['force_accounts'] ?? [],
        ];
        $aggregator = \Billrun_Aggregator::getInstance($options);
        $aggregator->load();
        $aggregator->aggregate();
    }

    /**
     * Note: as in the legacy test, a config file is added once per run - the
     * first multiDayCycle call decides the multi_day_cycle value for the rest of
     * the run (the next calls do not override it).
     */
    protected function multiDayCycle(array $case): void
    {
        if (!empty($case['row']['multiDayCycle'])) {
            \Billrun_Factory::config()->addConfig(self::DATA_PATH . 'conf/allow_premature_run.ini');
        } else {
            \Billrun_Factory::config()->addConfig(self::DATA_PATH . 'conf/multi_day_cycle_false.ini');
        }
    }

    protected function removeBillruns(array $case): void
    {
        \Billrun_Factory::db()->billing_cycleCollection()->remove(['billrun_key' => ['$ne' => 'abc']]);
        \Billrun_Factory::db()->billrunCollection()->remove(['billrun_key' => ['$ne' => 'abc']]);
    }
}
