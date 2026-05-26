<?php

/**
 * CustomerPricing tax-related tests - Codeception port of Taxmappingtest.php
 *
 * Covers:
 *  - CDR tax mapping: no-tax, default, global, override, fallback (Tests 1-6)
 *  - Billing-cycle tax on plan/service/discount lines (Tests 7-10)
 *  - Tax rounding rules: up/down/nearest x 0-2 decimals + empty (Tests 11-19)
 *
 * Fixture data is loaded from library/Tests/TaxmappingtestData/ before every test.
 * Rounding-specific rates are inserted directly with their fixture ObjectIds so the
 * pre-seeded line documents resolve correctly.
 *
 * @package         Tests
 * @copyright       Copyright (C) 2012-2024 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */
class customerPricingTaxCest
{
    protected $epsilon = 0.000001;
    protected $fixturesPath;

    // =========================================================================
    // LIFECYCLE
    // =========================================================================

    public function _before(ApiTester $I)
    {
        $this->fixturesPath = APPLICATION_PATH . '/library/Tests/TaxmappingtestData/';
        $this->cleanCollections();
        $this->loadFixtures();
        $this->insertRoundingRates();
        \Billrun_Config::getInstance()->loadDbConfig();
    }

    // =========================================================================
    // FIXTURE SETUP
    // =========================================================================

    protected function cleanCollections()
    {
        $collections = [
            'plans', 'services', 'subscribers', 'rates', 'lines',
            'balances', 'discounts', 'billrun', 'billing_cycle',
        ];
        foreach ($collections as $colName) {
            \Billrun_Factory::db()->{$colName . 'Collection'}()->remove([null]);
        }
        \Billrun_Factory::db()->taxesCollection()->remove([null]);
    }

    protected function loadFixtures()
    {
        $filesToLoad = [
            'plans', 'services', 'subscribers',
            'rates', 'lines', 'taxes', 'discounts', 'balances',
        ];
        foreach ($filesToLoad as $fileName) {
            $filePath = $this->fixturesPath . $fileName . '.json';
            if (!file_exists($filePath)) {
                continue;
            }
            $parsedData = json_decode(file_get_contents($filePath), true);
            if (empty($parsedData['data'])) {
                continue;
            }
            $data = $this->fixData($parsedData['data'], $fileName);
            \Billrun_Factory::db()->{$parsedData['collection']}()->batchInsert($data);
        }
    }

    /**
     * Insert rounding-test rates with the exact ObjectIds referenced in the lines.json
     * fixture so that the arate DBRefs on those lines resolve correctly.
     * All rounding rates use taxation=default (-> DEFAULT_VAT, 17%).
     */
    protected function insertRoundingRates()
    {
        $ratesCol = \Billrun_Factory::db()->ratesCollection();
        $from = new \Mongodloid_Date(strtotime('2017-11-01'));
        $to   = new \Mongodloid_Date(strtotime('2167-01-10'));
        $tax  = [['type' => 'vat', 'taxation' => 'default']];
        $baseRate = [
            'call' => [
                'BASE' => [
                    'rate' => [[
                        'from' => 0, 'to' => 'UNLIMITED', 'interval' => 1, 'price' => 1.5,
                        'uom_display' => ['range' => 'seconds', 'interval' => 'seconds'],
                    ]],
                ],
            ],
        ];

        $roundingRates = [
            ['_id' => new \Mongodloid_Id('616be87027577055c727bd17'), 'key' => 'ROUNDING_UP'],
            ['_id' => new \Mongodloid_Id('616bf6901c2308766075c3b2'), 'key' => 'ROUNDING_UP_DECIMALS1'],
            ['_id' => new \Mongodloid_Id('616bf6f63dc252542467e182'), 'key' => 'ROUNDING_UP_DECIMALS2'],
            ['_id' => new \Mongodloid_Id('616bf7893cc28928d15c0108'), 'key' => 'ROUNDING_DOWN'],
            ['_id' => new \Mongodloid_Id('616bf790cd8cda7c626e5993'), 'key' => 'ROUNDING_DOWN_DECIMALS1'],
            ['_id' => new \Mongodloid_Id('616bf7967acbd706ba142a34'), 'key' => 'ROUNDING_DOWN_DECIMALS2'],
            ['_id' => new \Mongodloid_Id('616c00913dc252542467e184'), 'key' => 'ROUNDING_NEAREST'],
            ['_id' => new \Mongodloid_Id('616c00973cc28928d15c010a'), 'key' => 'ROUNDING_NEAREST_DECIMALS1'],
            ['_id' => new \Mongodloid_Id('616c009dcd8cda7c626e5995'), 'key' => 'ROUNDING_NEAREST_DECIMALS2'],
            ['_id' => new \Mongodloid_Id('616c009dcd8cda7c626e5996'), 'key' => 'ROUNDING_EMPTY'],
        ];

        foreach ($roundingRates as $rate) {
            $ratesCol->insert(array_merge($rate, [
                'from'           => $from,
                'to'             => $to,
                'description'    => $rate['key'],
                'pricing_method' => 'tiered',
                'tariff_category'=> 'retail',
                'add_to_retail'  => false,
                'tax'            => $tax,
                'rates'          => $baseRate,
                'params'         => [],
            ]));
        }
    }

    // =========================================================================
    // DATA HELPERS (mirrors Tests_SetUp trait)
    // =========================================================================

    protected function fixData(array $data, string $collectionName): array
    {
        foreach ($data as $key => $item) {
            $data[$key] = $this->fixArrayDates($item);
        }
        foreach ($data as $key => $item) {
            $data[$key] = $this->fixDBobjID($item);
        }
        foreach ($data as $key => $item) {
            $data[$key] = $this->fixDbRef($item);
        }
        if ($collectionName === 'config') {
            $data[0]['urt'] = new \MongoDB\BSON\UTCDateTime();
        }
        return $data;
    }

    protected function fixArrayDates($value)
    {
        if (!is_array($value)) {
            if (is_string($value)) {
                $parts = explode('*', $value);
                if (count($parts) === 2 && $parts[0] === 'time') {
                    return new \Mongodloid_Date(strtotime($parts[1]));
                }
            }
            return $value;
        }
        foreach ($value as $field => $v) {
            $value[$field] = $this->fixArrayDates($v);
        }
        return $value;
    }

    protected function fixDBobjID(array $data): array
    {
        if (isset($data['OBJID'])) {
            $data['_id'] = new \Mongodloid_Id($data['OBJID']);
            unset($data['OBJID']);
        }
        return $data;
    }

    protected function fixDbRef(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                if (!empty($value['isDbRef'])) {
                    $data[$key] = \Billrun_Factory::db()
                        ->getCollection($value['collection'])
                        ->createRefByEntity(['_id' => new \Mongodloid_Id($value['ObjectId'])]);
                } else {
                    $data[$key] = $this->fixDbRef($value);
                }
            }
        }
        return $data;
    }

    // =========================================================================
    // CALCULATOR HELPERS
    // =========================================================================

    /**
     * Run the tax calculator on a line identified by its stamp.
     * Writes the updated entity back to the DB and returns the raw document.
     */
    protected function runPricingCalc(string $stamp): array
    {
        $linesCol = \Billrun_Factory::db()->linesCollection();
        $entity   = $linesCol->query(['stamp' => $stamp])->cursor()->current();
        $pricingCalc  = \Billrun_Calculator::getInstance(['type' => 'customerPricing', 'autoload' => false]);
        $pricingCalc->updateRow($entity);
        $pricingCalc->writeLine($entity, '123');
        return $entity->getRawData();
    }

    /**
     * Run the customer billing-cycle aggregator for a single account and return
     * all lines written for that account in the given billrun key.
     */
    protected function runBillingCycle(int $aid, string $stamp = '201905'): array
    {
        $aggregator = \Billrun_Aggregator::getInstance([
            'type'           => 'customer',
            'stamp'          => $stamp,
            'page'           => 0,
            'size'           => 100,
            'fetchonly'      => true,
            'generate_pdf'   => 0,
            'force_accounts' => [$aid],
        ]);
        $aggregator->load();
        $aggregator->aggregate();

        $allLines = [];
        foreach (\Billrun_Factory::db()->linesCollection()
            ->query(['aid' => $aid, 'billrun' => $stamp])
            ->cursor() as $line) {
            $allLines[] = $line->getRawData();
        }
        return $allLines;
    }

    /**
     * Find a cycle line by its type and key/name.
     * Replicates the logic in Taxmappingtest::getRow().
     */
    protected function getCycleLineByKey(array $lines, string $type, string $key): ?array
    {
        foreach ($lines as $line) {
            $typeAndKeyMatch = $line['type'] === $type
                && isset($line['key'])
                && $line['key'] === $key;
            $nameMatch = isset($line['name']) && $line['name'] === $key;
            if ($typeAndKeyMatch || $nameMatch) {
                return $line;
            }
        }
        return null;
    }

    // =========================================================================
    // ASSERTION HELPERS
    // =========================================================================

    /**
     * Assert tax_data fields on a line document.
     * Compares floats with $this->epsilon.
     * Pass 'taxes' => [] in $expected to assert the taxes array is empty.
     */
    protected function assertTaxData(ApiTester $I, array $line, array $expected, string $context = ''): void
    {
        $taxData = $line['tax_data'] ?? [];
        $prefix  = $context ? "[$context] " : '';

        \PHPUnit\Framework\Assert::assertEqualsWithDelta(
            $expected['total_amount'],
            $taxData['total_amount'] ?? null,
            $this->epsilon,
            "{$prefix}total_amount"
        );

        if (isset($expected['total_tax'])) {
            \PHPUnit\Framework\Assert::assertEqualsWithDelta(
                $expected['total_tax'],
                $taxData['total_tax'] ?? null,
                $this->epsilon,
                "{$prefix}total_tax"
            );
        }

        if (array_key_exists('taxes', $expected)) {
            if (empty($expected['taxes'])) {
                \PHPUnit\Framework\Assert::assertEmpty(
                    $taxData['taxes'] ?? [],
                    "{$prefix}taxes should be empty"
                );
            } else {
                foreach ($expected['taxes'] as $i => $expectedTax) {
                    \PHPUnit\Framework\Assert::assertEqualsWithDelta(
                        $expectedTax['tax'],
                        $taxData['taxes'][$i]['tax'] ?? null,
                        $this->epsilon,
                        "{$prefix}taxes[$i].tax"
                    );
                    \PHPUnit\Framework\Assert::assertEqualsWithDelta(
                        $expectedTax['amount'],
                        $taxData['taxes'][$i]['amount'] ?? null,
                        $this->epsilon,
                        "{$prefix}taxes[$i].amount"
                    );
                    $I->assertEquals(
                        $expectedTax['key'],
                        $taxData['taxes'][$i]['key'] ?? null,
                        "{$prefix}taxes[$i].key"
                    );
                }
            }
        }
    }

    /**
     * Assert rounding-related fields: final_charge, aprice, before_rounding,
     * and tax_data amounts.
     */
    protected function assertRoundingData(ApiTester $I, array $line, array $expected): void
    {
        if (isset($expected['final_charge'])) {
            \PHPUnit\Framework\Assert::assertEqualsWithDelta(
                $expected['final_charge'],
                $line['final_charge'] ?? null,
                $this->epsilon,
                'final_charge'
            );
        }
        if (isset($expected['aprice'])) {
            \PHPUnit\Framework\Assert::assertEqualsWithDelta(
                $expected['aprice'],
                $line['aprice'] ?? null,
                $this->epsilon,
                'aprice'
            );
        }
        if (isset($expected['before_rounding']['final_charge'])) {
            \PHPUnit\Framework\Assert::assertEqualsWithDelta(
                $expected['before_rounding']['final_charge'],
                $line['before_rounding']['final_charge'] ?? null,
                $this->epsilon,
                'before_rounding.final_charge'
            );
        }
        if (isset($expected['before_rounding']['aprice'])) {
            \PHPUnit\Framework\Assert::assertEqualsWithDelta(
                $expected['before_rounding']['aprice'],
                $line['before_rounding']['aprice'] ?? null,
                $this->epsilon,
                'before_rounding.aprice'
            );
        }
        if (isset($expected['tax_data']['total_amount'])) {
            \PHPUnit\Framework\Assert::assertEqualsWithDelta(
                $expected['tax_data']['total_amount'],
                $line['tax_data']['total_amount'] ?? null,
                $this->epsilon,
                'tax_data.total_amount'
            );
        }
        if (isset($expected['tax_data']['total_amount_before_rounding'])) {
            \PHPUnit\Framework\Assert::assertEqualsWithDelta(
                $expected['tax_data']['total_amount_before_rounding'],
                $line['tax_data']['total_amount_before_rounding'] ?? null,
                $this->epsilon,
                'tax_data.total_amount_before_rounding'
            );
        }
    }

    // =========================================================================
    // CDR TESTS  (Tests 1-6)
    // =========================================================================

    /**
     * Test 1 - Rate with taxation=no: no tax should be applied.
     * Rate: NONTAX_CALL | Plan: NONTAX_PLAN | aprice: 100
     */
    public function testCdrNonTaxRate(ApiTester $I): void
    {
        $line = $this->runPricingCalc('2a59f077692f3247811d50598b64e892');

        $I->assertEquals(1, $line['aid']);
        $I->assertEquals(2, $line['sid']);
        $this->assertTaxData($I, $line, [
            'total_amount' => 0,
            'total_tax'    => 0,
            'taxes'        => [],
        ]);
    }

    /**
     * Test 2 - Rate with taxation=default -> DEFAULT_VAT (17%).
     * Rate: DEFAULT_TAX_CALL | Plan: NONTAX_PLAN | aprice: 100
     */
    public function testCdrRateUsesDefaultTax(ApiTester $I): void
    {
        $line = $this->runPricingCalc('0f721f8984107a5cc3e3e3a6b06babd2');

        $I->assertEquals(1, $line['aid']);
        $I->assertEquals(2, $line['sid']);
        $this->assertTaxData($I, $line, [
            'total_amount' => 17,
            'total_tax'    => 0.17,
            'taxes'        => [['tax' => 0.17, 'amount' => 17, 'key' => 'DEFAULT_VAT']],
        ]);
    }

    /**
     * Test 3 - Rate with taxation=global -> resolves to DEFAULT_VAT via global mapping.
     * Rate: USE_GLOBAL_TAX_CALL | Plan: NONTAX_PLAN | aprice: 100
     */
    public function testCdrRateUsesGlobalTaxMapping(ApiTester $I): void
    {
        $line = $this->runPricingCalc('54c97046cd6cfcd877f99783e1f48d2c');

        $I->assertEquals(1, $line['aid']);
        $I->assertEquals(2, $line['sid']);
        $this->assertTaxData($I, $line, [
            'total_amount' => 17,
            'total_tax'    => 0.17,
            'taxes'        => [['tax' => 0.17, 'amount' => 17, 'key' => 'DEFAULT_VAT']],
        ]);
    }

    /**
     * Test 4 - Rate with taxation=custom, custom_logic=override, custom_tax=A -> tax "A" (10%).
     * Rate: OVERRIDE_GLOBAL_TAX_CALL | Plan: NONTAX_PLAN | aprice: 10
     */
    public function testCdrRateOverridesGlobalTaxMapping(ApiTester $I): void
    {
        $line = $this->runPricingCalc('7d9ea6298c17a9336410592f09fb1c65');

        $I->assertEquals(1, $line['aid']);
        $I->assertEquals(2, $line['sid']);
        $this->assertTaxData($I, $line, [
            'total_amount' => 1,
            'total_tax'    => 0.1,
            'taxes'        => [['tax' => 0.1, 'amount' => 1, 'key' => 'A']],
        ]);
    }

    /**
     * Test 5 - Rate with fallback logic; plan has no global mapping match -> fallback to tax "A" (10%).
     * Rate: FALLBACK_TAX_CALL | Plan: NONTAX_PLAN | aprice: 10
     */
    public function testCdrRateFallbackToCustomTaxWhenGlobalNotFound(ApiTester $I): void
    {
        $line = $this->runPricingCalc('169788ab8db858b08659e74db90185f5');

        $I->assertEquals(1, $line['aid']);
        $I->assertEquals(2, $line['sid']);
        $this->assertTaxData($I, $line, [
            'total_amount' => 1,
            'total_tax'    => 0.1,
            'taxes'        => [['tax' => 0.1, 'amount' => 1, 'key' => 'A']],
        ]);
    }

    /**
     * Test 6 - Rate with fallback logic; plan has a global mapping match (OVERRIDE_GLOBAL_TAX_PLAN ->
     * tax "A") -> uses the global result, also "A" (10%).
     * Rate: FALLBACK_TAX_CALL | Plan: OVERRIDE_GLOBAL_TAX_PLAN | aprice: 10
     */
    public function testCdrRateFallbackUsesGlobalMappingWhenFound(ApiTester $I): void
    {
        $line = $this->runPricingCalc('7d9ea6298c17a9336410592f09fb1c66');

        $I->assertEquals(1, $line['aid']);
        $I->assertEquals(3, $line['sid']);
        $this->assertTaxData($I, $line, [
            'total_amount' => 1,
            'total_tax'    => 0.1,
            'taxes'        => [['tax' => 0.1, 'amount' => 1, 'key' => 'A']],
        ]);
    }

    // =========================================================================
    // CYCLE TESTS  (Tests 7-10)
    // =========================================================================

    /**
     * Test 7 - Billing cycle for aid=1 (sid=2): plan NONTAX_PLAN, service NONTAX_SERVICE,
     * discount NOT_VAT_PLANANDSERVICE -> all lines have zero tax.
     */
    public function testCycleNonTaxPlanServiceDiscount(ApiTester $I): void
    {
        $lines = $this->runBillingCycle(1);

        $creditLine = $this->getCycleLineByKey($lines, 'credit', 'NOT_VAT_PLANANDSERVICE');
        if ($creditLine) {
            $this->assertTaxData($I, $creditLine, ['total_amount' => 0, 'total_tax' => 0], 'credit NOT_VAT_PLANANDSERVICE');
        }

        $flatLine = $this->getCycleLineByKey($lines, 'flat', 'NONTAX_PLAN');
        if ($flatLine) {
            $this->assertTaxData($I, $flatLine, ['total_amount' => 0, 'total_tax' => 0], 'flat NONTAX_PLAN');
        }

        $serviceLine = $this->getCycleLineByKey($lines, 'services', 'NONTAX_SERVICE');
        if ($serviceLine) {
            $this->assertTaxData($I, $serviceLine, ['total_amount' => 0, 'total_tax' => 0], 'services NONTAX_SERVICE');
        }
    }

    /**
     * Test 8 - Billing cycle for aid=9 (sid=10): plan USE_GLOBAL_TAX_PLAN, service
     * USE_GLOBAL_TAX_SERVICE, discount USE_GLOBAL_PLANANDSERVICE -> DEFAULT_VAT (17%).
     */
    public function testCycleGlobalTaxPlanServiceDiscount(ApiTester $I): void
    {
        $lines = $this->runBillingCycle(9);

        $creditLine = $this->getCycleLineByKey($lines, 'credit', 'USE_GLOBAL_PLANANDSERVICE');
        if ($creditLine) {
            $this->assertTaxData($I, $creditLine, ['total_amount' => -1.7, 'total_tax' => 0.17], 'credit USE_GLOBAL_PLANANDSERVICE');
        }

        $flatLine = $this->getCycleLineByKey($lines, 'flat', 'USE_GLOBAL_TAX_PLAN');
        if ($flatLine) {
            $this->assertTaxData($I, $flatLine, ['total_amount' => 17, 'total_tax' => 0.17], 'flat USE_GLOBAL_TAX_PLAN');
        }

        $serviceLine = $this->getCycleLineByKey($lines, 'services', 'USE_GLOBAL_TAX_SERVICE');
        if ($serviceLine) {
            $this->assertTaxData($I, $serviceLine, ['total_amount' => 1.7, 'total_tax' => 0.17], 'services USE_GLOBAL_TAX_SERVICE');
        }
    }

    /**
     * Test 9 - Billing cycle for aid=11 (sid=12): plan OVERRIDE_GLOBAL_TAX_PLAN (tax A, 10%),
     * service OVERRIDE_GLOBAL_TAX_SERVICE (tax B, 20%), discount with both taxes.
     */
    public function testCycleOverrideGlobalTaxPlanServiceDiscount(ApiTester $I): void
    {
        $lines = $this->runBillingCycle(11);

        $creditLine = $this->getCycleLineByKey($lines, 'credit', 'OVERRIDE_GLOBAL_TAX_PLANANDSERVICE');
        if ($creditLine) {
            $this->assertTaxData($I, $creditLine, [
                'total_amount' => -1.5,
                'total_tax'    => 0.15,
                'taxes'        => [
                    ['tax' => 0.1, 'amount' => -0.5, 'key' => 'A'],
                    ['tax' => 0.2, 'amount' => -1,   'key' => 'B'],
                ],
            ], 'credit OVERRIDE_GLOBAL_TAX_PLANANDSERVICE');
        }

        $flatLine = $this->getCycleLineByKey($lines, 'flat', 'OVERRIDE_GLOBAL_TAX_PLAN');
        if ($flatLine) {
            $this->assertTaxData($I, $flatLine, ['total_amount' => 10, 'total_tax' => 0.1], 'flat OVERRIDE_GLOBAL_TAX_PLAN');
        }

        $serviceLine = $this->getCycleLineByKey($lines, 'services', 'OVERRIDE_GLOBAL_TAX_SERVICE');
        if ($serviceLine) {
            $this->assertTaxData($I, $serviceLine, ['total_amount' => 2, 'total_tax' => 0.2], 'services OVERRIDE_GLOBAL_TAX_SERVICE');
        }
    }

    /**
     * Test 10 - Billing cycle for aid=13 (sid=14): plan FALLBACK_TAX_PLAN (fallback -> tax A, 10%),
     * service FALLBACK_TAX_SERVICE (fallback -> tax B, 20%).
     */
    public function testCycleFallbackTaxPlanService(ApiTester $I): void
    {
        $lines = $this->runBillingCycle(13);

        $flatLine = $this->getCycleLineByKey($lines, 'flat', 'FALLBACK_TAX_PLAN');
        if ($flatLine) {
            $this->assertTaxData($I, $flatLine, ['total_amount' => 10, 'total_tax' => 0.1], 'flat FALLBACK_TAX_PLAN');
        }

        $serviceLine = $this->getCycleLineByKey($lines, 'services', 'FALLBACK_TAX_SERVICE');
        if ($serviceLine) {
            $this->assertTaxData($I, $serviceLine, ['total_amount' => 2, 'total_tax' => 0.2], 'services FALLBACK_TAX_SERVICE');
        }
    }

    // =========================================================================
    // ROUNDING TESTS  (Tests 11-19 + empty)
    // =========================================================================
    // All rounding lines start with aprice=1.5 and use DEFAULT_VAT (17%).
    // Pre-rounding total = 1.5 x 1.17 = 1.755.  The rounding_rules on each
    // line dictate how final_charge is rounded; aprice and tax are then
    // back-calculated proportionally.

    /**
     * Test 11 - Round UP to 0 decimals: 1.755 -> 2.
     */
    public function testRoundingUpNoDecimals(ApiTester $I): void
    {
        $line = $this->runPricingCalc('900f025a36c8ce1e32eda0a8b24e9a69');

        $this->assertRoundingData($I, $line, [
            'final_charge'    => 2,
            'aprice'          => 1.7094017094017095,
            'before_rounding' => ['final_charge' => 1.755, 'aprice' => 1.5],
            'tax_data'        => ['total_amount' => 0.2905982905982906, 'total_amount_before_rounding' => 0.255],
        ]);
    }

    /**
     * Test 12 - Round UP to 1 decimal: 1.755 -> 1.8.
     */
    public function testRoundingUpOneDecimal(ApiTester $I): void
    {
        $line = $this->runPricingCalc('feaa63e389a04d9607dfa0645d3cef1d');

        $this->assertRoundingData($I, $line, [
            'final_charge'    => 1.8,
            'aprice'          => 1.5384615384615388,
            'before_rounding' => ['final_charge' => 1.755, 'aprice' => 1.5],
            'tax_data'        => ['total_amount' => 0.26153846153846155, 'total_amount_before_rounding' => 0.255],
        ]);
    }

    /**
     * Test 13 - Round UP to 2 decimals: 1.755 -> 1.76.
     */
    public function testRoundingUpTwoDecimals(ApiTester $I): void
    {
        $line = $this->runPricingCalc('ae9020df9411a9bbf7f9d73968ff5e4b');

        $this->assertRoundingData($I, $line, [
            'final_charge'    => 1.76,
            'aprice'          => 1.5042735042735043,
            'before_rounding' => ['final_charge' => 1.755, 'aprice' => 1.5],
            'tax_data'        => ['total_amount' => 0.25572649572649575, 'total_amount_before_rounding' => 0.255],
        ]);
    }

    /**
     * Test 14 - Round DOWN to 0 decimals: 1.755 -> 1.
     */
    public function testRoundingDownNoDecimals(ApiTester $I): void
    {
        $line = $this->runPricingCalc('f70cf4bb941d16d1d91ea331d4373ab0');

        $this->assertRoundingData($I, $line, [
            'final_charge'    => 1,
            'aprice'          => 0.8547008547008548,
            'before_rounding' => ['final_charge' => 1.755, 'aprice' => 1.5],
            'tax_data'        => ['total_amount' => 0.1452991452991453, 'total_amount_before_rounding' => 0.255],
        ]);
    }

    /**
     * Test 15 - Round DOWN to 1 decimal: 1.755 -> 1.7.
     */
    public function testRoundingDownOneDecimal(ApiTester $I): void
    {
        $line = $this->runPricingCalc('ac3a827b3ad76df44605ff2466b591dc');

        $this->assertRoundingData($I, $line, [
            'final_charge'    => 1.7,
            'aprice'          => 1.4529914529914532,
            'before_rounding' => ['final_charge' => 1.755, 'aprice' => 1.5],
            'tax_data'        => ['total_amount' => 0.24700854700854702, 'total_amount_before_rounding' => 0.255],
        ]);
    }

    /**
     * Test 16 - Round DOWN to 2 decimals: 1.755 -> 1.75.
     */
    public function testRoundingDownTwoDecimals(ApiTester $I): void
    {
        $line = $this->runPricingCalc('5e6e0b9b481e5c03f61df07aeef56e8b');

        $this->assertRoundingData($I, $line, [
            'final_charge'    => 1.75,
            'aprice'          => 1.4957264957264957,
            'before_rounding' => ['final_charge' => 1.755, 'aprice' => 1.5],
            'tax_data'        => ['total_amount' => 0.2542735042735043, 'total_amount_before_rounding' => 0.255],
        ]);
    }

    /**
     * Test 17 - Round NEAREST to 0 decimals: 1.755 -> 2.
     */
    public function testRoundingNearestNoDecimals(ApiTester $I): void
    {
        $line = $this->runPricingCalc('45bd347b51b7235ef91fcac3652ed498');

        $this->assertRoundingData($I, $line, [
            'final_charge'    => 2,
            'aprice'          => 1.7094017094017095,
            'before_rounding' => ['final_charge' => 1.755, 'aprice' => 1.5],
            'tax_data'        => ['total_amount' => 0.2905982905982906, 'total_amount_before_rounding' => 0.255],
        ]);
    }

    /**
     * Test 18 - Round NEAREST to 1 decimal: 1.755 -> 1.8.
     */
    public function testRoundingNearestOneDecimal(ApiTester $I): void
    {
        $line = $this->runPricingCalc('69a4914a231ae416625a9fc832f09b10');

        $this->assertRoundingData($I, $line, [
            'final_charge'    => 1.8,
            'aprice'          => 1.5384615384615388,
            'before_rounding' => ['final_charge' => 1.755, 'aprice' => 1.5],
            'tax_data'        => ['total_amount' => 0.26153846153846155, 'total_amount_before_rounding' => 0.255],
        ]);
    }

    /**
     * Test 19 - Round NEAREST to 2 decimals: 1.755 -> 1.76.
     */
    public function testRoundingNearestTwoDecimals(ApiTester $I): void
    {
        $line = $this->runPricingCalc('598283049a9d8e7b4accadca5fa0c50b');

        $this->assertRoundingData($I, $line, [
            'final_charge'    => 1.76,
            'aprice'          => 1.5042735042735043,
            'before_rounding' => ['final_charge' => 1.755, 'aprice' => 1.5],
            'tax_data'        => ['total_amount' => 0.25572649572649575, 'total_amount_before_rounding' => 0.255],
        ]);
    }

    /**
     * Test 19b - Rounding type is empty string -> no rounding applied; final_charge = 1.755.
     */
    public function testRoundingEmpty(ApiTester $I): void
    {
        $line = $this->runPricingCalc('598283049a9d8e7b4accadca5fa0c51b');

        $this->assertRoundingData($I, $line, [
            'final_charge' => 1.755,
            'aprice'       => 1.5,
            'tax_data'     => ['total_amount' => 0.255],
        ]);
    }
}
