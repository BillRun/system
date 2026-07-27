<?php

/**
 * CustomerPricing skip-calc test - ingests a CDR through the real input
 * processor (Billrun_Processor) with a file-type filter that adds
 * skip_calc=["pricing"] to matching rows. The pricing calculator must
 * therefore be skipped, so the resulting line must not have tax_data,
 * final_charge, aprice or billrun.
 *
 * Flow: CSV -> parser -> customer calc -> rate calc -> Processor::filterLines
 * adds skip_calc -> calcCpu plugin runs queue calculators
 * (QueueCalculators::shouldSkipCalc bypasses pricing).
 *
 * Also covers the BRCD-5250 queue-removal bug: a line stuck in the queue after
 * the customer calculator must be fully calculated AND dequeued by running the
 * rate + pricing calculators cron-style, without a separate tax run.
 *
 * @package         Tests
 * @copyright       Copyright (C) 2012-2025 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */
class customerPricingCest
{
    public static $isIPSet = false;

    protected $epsilon = 0.000001;
    protected $accountDetails;
    protected $planDetails;
    protected $rateDetails;
    protected $subscriberDetails;

    // =========================================================================
    // LIFECYCLE
    // =========================================================================

    public function _before(ApiTester $I)
    {
        if (!self::$isIPSet) {
            $this->setUP($I);
            self::$isIPSet = true;
            \Billrun_Config::getInstance()->loadDbConfig();
        }
        $I->cleanDB();
    }

    /**
     * One-time setup: register the skipPricingTest file_type (with the filter
     * that adds skip_calc=["pricing"]), the call usage_type, and the
     * queue.calculators list so calcCpu runs customer -> rate -> pricing.
     */
    protected function setUP(ApiTester $I)
    {
        $I->setSettings('file_types', $this->inputProcessor());
        $I->setSettings('usage_types', [[
            'usage_type'    => 'call',
            'label'         => 'call',
            'property_type' => 'time',
            'invoice_uom'   => 'seconds',
            'input_uom'     => 'seconds',
        ]]);
        \Billrun_Factory::config()->setConfigValue('queue.calculators', ['customer', 'rate', 'pricing']);
    }

    // =========================================================================
    // TESTS
    // =========================================================================

    /**
     * Filter condition matches uf.rate=CALL_SKIP. After processing:
     *   - line.skip_calc contains "pricing"
     *   - line has no tax_data / final_charge / aprice / billrun
     */
    public function testSkipCalcPricing(ApiTester $I): void
    {
        $this->createBaseData($I);

        $csvPath = 'tests/all/calculators/test_files/skip_pricing.csv';
        $I->resetBillrunSingletons();

        $options   = ['type' => 'skipPricingTest', 'path' => $csvPath];
        $processor = \Billrun_Processor::getInstance($options);
        $processor->processorByPath($options);

        $entity = \Billrun_Factory::db()->linesCollection()
            ->query(['file' => basename($csvPath)])
            ->cursor()->current();
        \PHPUnit\Framework\Assert::assertFalse(
            $entity->isEmpty(),
            'input processor must have created a line from the CDR'
        );
        $line = $entity->getRawData();

        $I->assertContains('pricing', $line['skip_calc'] ?? [], 'filter must add pricing to skip_calc');
        $I->assertArrayNotHasKey('tax_data',     $line, 'tax_data must not be added when pricing is skipped');
        $I->assertArrayNotHasKey('final_charge', $line, 'final_charge must not be added when pricing is skipped');
        $I->assertArrayNotHasKey('aprice',       $line, 'aprice must not be added when pricing is skipped');
        $I->assertArrayNotHasKey('billrun',      $line, 'billrun must not be added when pricing is skipped');
        \PHPUnit\Framework\Assert::assertEmpty(
            $this->getBalanceBySid((int) $line['sid'], $this->getLineBillrunKey($line)),
            'no balance may be created/charged when pricing is skipped'
        );
    }

    /**
     * BRCD-5250 - a line stuck in the queue after the customer calculator
     * (queue calc_name=customer) is later completed by running the rate and
     * pricing calculators cron-style (calc -> write -> removeFromQueue),
     * with the production queue chain (customer, rate, pricing, tax).
     *
     * Since the tax calculator was merged into the pricing calculator
     * (updateRow -> applyTaxToRow), once pricing ran the line is fully
     * calculated (aprice/billrun/tax_data/final_charge) and the merged tax
     * stage is completed as well, so the queue entry must be removed right
     * after pricing - without a separate tax calculator run.
     */
    public function testStuckQueueLineRemovedAfterRateAndPricing(ApiTester $I): void
    {
        $this->createBaseData($I);

        $originalCalculators = \Billrun_Factory::config()->getConfigValue('queue.calculators');
        \Billrun_Factory::config()->setConfigValue('queue.calculators', ['customer', 'rate', 'pricing', 'tax']);

        try {
            $csvPath = 'tests/all/calculators/test_files/stuck_queue.csv';
            $stamp   = $this->ingestStuckLine($I, $csvPath);

            // the missing rate now exists, so the cron calculators can finish the line
            $this->generateStuckRate($I);

            $this->runQueueCalculator($I, 'rate_Usage');
            $queueRow = $this->getQueueRowByStamp($stamp);
            $I->assertEquals('rate', $queueRow['calc_name'] ?? null, 'rate calculator must advance the stuck line to calc_name=rate');

            $this->runQueueCalculator($I, 'customerPricing');

            $this->assertLineFullyCalculated($I, $csvPath);

            $queueRow = $this->getQueueRowByStamp($stamp);
            \PHPUnit\Framework\Assert::assertEmpty(
                $queueRow,
                'BRCD-5250: the line must be removed from the queue right after the pricing calculator (which already applies tax) - a separate tax calculator run must not be required'
            );
        } finally {
            \Billrun_Factory::config()->setConfigValue('queue.calculators', $originalCalculators);
        }
    }

    /**
     * BRCD-5250 - same stuck-line recovery, but with a queue chain that has a
     * calculator AFTER the merged pricing+tax stages (customer, rate, pricing,
     * tax, unify). Here pricing must NOT remove the line from the queue: the
     * line is fully calculated, but it still has to reach unify, so pricing
     * only advances the queue entry past the merged tax stage (calc_name=tax).
     * Unify, being the last queue calculator, then removes it from the queue.
     */
    public function testStuckQueueLineWaitsForUnifyAfterPricing(ApiTester $I): void
    {
        $this->createBaseData($I);

        $originalCalculators = \Billrun_Factory::config()->getConfigValue('queue.calculators');
        \Billrun_Factory::config()->setConfigValue('queue.calculators', ['customer', 'rate', 'pricing', 'tax', 'unify']);

        try {
            $csvPath = 'tests/all/calculators/test_files/stuck_queue.csv';
            $stamp   = $this->ingestStuckLine($I, $csvPath);

            $this->generateStuckRate($I);

            $this->runQueueCalculator($I, 'rate_Usage');
            $this->runQueueCalculator($I, 'customerPricing');

            $this->assertLineFullyCalculated($I, $csvPath);

            $queueRow = $this->getQueueRowByStamp($stamp);
            \PHPUnit\Framework\Assert::assertNotEmpty(
                $queueRow,
                'the line must stay in the queue after pricing when a later calculator (unify) is configured'
            );
            $I->assertEquals('tax', $queueRow['calc_name'] ?? null, 'pricing must advance the queue entry past the merged tax stage so unify picks it up');

            $this->runQueueCalculator($I, 'unify');
            $queueRow = $this->getQueueRowByStamp($stamp);
            \PHPUnit\Framework\Assert::assertEmpty(
                $queueRow,
                'unify (the last queue calculator) must remove the line from the queue'
            );
        } finally {
            \Billrun_Factory::config()->setConfigValue('queue.calculators', $originalCalculators);
        }
    }

    /**
     * BRCD-5250 - cost_with_tax on the balance when part of the usage is
     * covered by a service group: the subscriber has a service whose group
     * includes 5 of the 10 used seconds for rate CALL_STUCK, so pricing
     * charges only the out-of-group half. The balance must accumulate the
     * covered usage under balance.groups.<group>, and only the charged half -
     * tax included - under cost / cost_with_tax.
     */
    public function testCostWithTaxBalanceWithServiceGroup(ApiTester $I): void
    {
        // service whose group covers 5 seconds of CALL_STUCK usage
        $I->generateService([
            'name'        => 'GROUP_SERVICE',
            'description' => 'group service',
            'from'        => '2025-01-01',
            'include'     => [
                'groups' => [
                    'CALL_STUCK_GROUP' => [
                        'usage_types'       => ['call' => ['unit' => 'seconds']],
                        'value'             => 5,
                        'rates'             => ['CALL_STUCK'],
                        'account_shared'    => false,
                        'account_pool'      => false,
                        'quantity_affected' => false,
                    ],
                ],
            ],
        ]);

        // subscriber holds the service, so the customer calculator copies it
        // to the line (services_data) and pricing applies the group
        $this->createBaseData($I, [], [], [], [
            'services' => [['name' => 'GROUP_SERVICE', 'from' => '2025-01-01', 'to' => '2126-01-01']],
        ]);
        $this->generateStuckRate($I); // rate exists up front - the line is processed end to end

        $originalCalculators = \Billrun_Factory::config()->getConfigValue('queue.calculators');
        \Billrun_Factory::config()->setConfigValue('queue.calculators', ['customer', 'rate', 'pricing', 'tax']);

        try {
            $csvPath = 'tests/all/calculators/test_files/stuck_queue.csv';
            $I->resetBillrunSingletons();

            $options   = ['type' => 'skipPricingTest', 'path' => $csvPath];
            $processor = \Billrun_Processor::getInstance($options);
            $processor->processorByPath($options);

            $line = $this->getLineByFile($csvPath);
            \PHPUnit\Framework\Assert::assertNotEmpty($line, 'input processor must have created a line from the CDR');
            $I->assertArrayHasKey('aprice', $line, 'line must be priced');

            // 5 of the 10 seconds are covered by the service group, 5 are charged
            \PHPUnit\Framework\Assert::assertEqualsWithDelta(5, $line['in_group'] ?? null, $this->epsilon, '5 seconds must be covered by the service group');
            \PHPUnit\Framework\Assert::assertEqualsWithDelta(5, $line['aprice'], $this->epsilon, 'only the out-of-group 5 seconds may be charged');
            \PHPUnit\Framework\Assert::assertEqualsWithDelta(
                $line['aprice'] + ($line['tax_data']['total_amount'] ?? 0),
                $line['final_charge'],
                $this->epsilon,
                'final_charge must be aprice plus the tax amount'
            );

            $billrunKey = $this->getLineBillrunKey($line);

            // the covered usage accrues on the service's own balance document
            $serviceBalance = $this->getBalanceBySid((int) $line['sid'], $billrunKey, 'GROUP_SERVICE');
            \PHPUnit\Framework\Assert::assertNotEmpty($serviceBalance, 'pricing must create a balance for the service');
            $group = $serviceBalance['balance']['groups']['CALL_STUCK_GROUP'] ?? [];
            \PHPUnit\Framework\Assert::assertEqualsWithDelta(5, $group['usagev'] ?? null, $this->epsilon, 'service group must accumulate the covered usage');
            \PHPUnit\Framework\Assert::assertEqualsWithDelta(0, $group['left'] ?? null, $this->epsilon, 'service group allowance must be exhausted');

            // the charged (out-of-group) part accrues on the main balance
            $balance = $this->getBalanceBySid((int) $line['sid'], $billrunKey);
            \PHPUnit\Framework\Assert::assertNotEmpty($balance, 'pricing must create a balance for the subscriber');
            $totals = $balance['balance']['totals'][$line['usaget']] ?? [];
            \PHPUnit\Framework\Assert::assertEqualsWithDelta($line['aprice'], $totals['cost'] ?? null, $this->epsilon, 'balance usage-type cost must accumulate only the charged (out-of-group) part');
            \PHPUnit\Framework\Assert::assertEqualsWithDelta($line['final_charge'], $totals['cost_with_tax'] ?? null, $this->epsilon, 'balance usage-type cost_with_tax must accumulate only the charged part, tax included');
            \PHPUnit\Framework\Assert::assertEqualsWithDelta($line['aprice'], $balance['balance']['cost'] ?? null, $this->epsilon, 'balance cost must accumulate only the charged part');
            \PHPUnit\Framework\Assert::assertEqualsWithDelta($line['final_charge'], $balance['balance']['cost_with_tax'] ?? null, $this->epsilon, 'balance cost_with_tax must accumulate only the charged part, tax included');
        } finally {
            \Billrun_Factory::config()->setConfigValue('queue.calculators', $originalCalculators);
        }
    }

    // =========================================================================
    // CALCULATOR HELPERS
    // =========================================================================

    /**
     * The billing-cycle key of the balance the line charged: the billrun field
     * pricing stamped on it, as long as the usage time falls inside that cycle.
     * Balances are scoped to the usage time's cycle, while pricing stamps a CDR
     * of an already-closed cycle with the currently open invoicing cycle - in
     * that case (and when the line was not priced at all, e.g. pricing skipped)
     * fall back to the usage time's cycle.
     */
    protected function getLineBillrunKey(array $line): string
    {
        if (isset($line['billrun'])) {
            $billrunKey = (string) $line['billrun'];
            if ($line['urt']->sec >= \Billrun_Billingcycle::getStartTime($billrunKey)
                && $line['urt']->sec < \Billrun_Billingcycle::getEndTime($billrunKey)) {
                return $billrunKey;
            }
        }
        return \Billrun_Billingcycle::getBillrunKeyByTimestamp($line['urt']->sec);
    }

    /**
     * Ingest a CDR whose rate (CALL_STUCK) does not exist yet - the rate
     * calculator fails during processing, so the line stays in the queue at
     * calc_name=customer. Returns the stuck line's stamp.
     */
    protected function ingestStuckLine(ApiTester $I, string $csvPath): string
    {
        $I->resetBillrunSingletons();

        $options   = ['type' => 'skipPricingTest', 'path' => $csvPath];
        $processor = \Billrun_Processor::getInstance($options);
        $processor->processorByPath($options);

        $line = $this->getLineByFile($csvPath);
        \PHPUnit\Framework\Assert::assertNotEmpty($line, 'input processor must have created a line from the CDR');

        $queueRow = $this->getQueueRowByStamp($line['stamp']);
        $I->assertEquals('customer', $queueRow['calc_name'] ?? null, 'line must be stuck in the queue after the customer calculator');

        return $line['stamp'];
    }

    /**
     * Create the CALL_STUCK rate the stuck CDR references, so the cron
     * calculators can finish the line.
     */
    protected function generateStuckRate(ApiTester $I): void
    {
        $I->generateRate([
            'tariff_category' => 'retail',
            'key'             => 'CALL_STUCK',
            'rates' => [
                'call' => [
                    'BASE' => [
                        'rate' => [[
                            'from'        => 0,
                            'to'          => 'UNLIMITED',
                            'interval'    => 1,
                            'price'       => 1,
                            'uom_display' => ['range' => 'seconds', 'interval' => 'seconds'],
                        ]],
                    ],
                ],
            ],
        ]);
    }

    /**
     * Assert the pricing calculator finished the line: priced it, applied tax
     * (tax is merged into pricing), and accumulated the charge on the
     * subscriber's balance - both the plain cost and the new tax-inclusive
     * cost_with_tax fields, which ride the same single balance update
     * (Billrun_Balance_Postpaid::buildBalanceUpdateQuery adds them to the
     * existing $inc - no additional queries to the balances collection).
     */
    protected function assertLineFullyCalculated(ApiTester $I, string $csvPath): void
    {
        $line = $this->getLineByFile($csvPath);
        $I->assertArrayHasKey('aprice',       $line, 'pricing calculator must price the stuck line');
        $I->assertArrayHasKey('billrun',      $line, 'pricing calculator must set billrun on the stuck line');
        $I->assertArrayHasKey('tax_data',     $line, 'pricing calculator must apply tax to the stuck line (tax is merged into pricing)');
        $I->assertArrayHasKey('final_charge', $line, 'pricing calculator must set final_charge on the stuck line');
        \PHPUnit\Framework\Assert::assertEqualsWithDelta(
            $line['aprice'] + ($line['tax_data']['total_amount'] ?? 0),
            $line['final_charge'],
            $this->epsilon,
            'final_charge must be aprice plus the tax amount'
        );

        $balance = $this->getBalanceBySid((int) $line['sid'], $this->getLineBillrunKey($line));
        \PHPUnit\Framework\Assert::assertNotEmpty($balance, 'pricing must create a balance for the subscriber');
        $totals = $balance['balance']['totals'][$line['usaget']] ?? [];
        \PHPUnit\Framework\Assert::assertEqualsWithDelta($line['aprice'], $totals['cost'] ?? null, $this->epsilon, 'balance usage-type cost must accumulate aprice');
        \PHPUnit\Framework\Assert::assertEqualsWithDelta($line['final_charge'], $totals['cost_with_tax'] ?? null, $this->epsilon, 'balance usage-type cost_with_tax must accumulate the tax-inclusive charge');
        \PHPUnit\Framework\Assert::assertEqualsWithDelta($line['aprice'], $balance['balance']['cost'] ?? null, $this->epsilon, 'balance cost must accumulate aprice');
        \PHPUnit\Framework\Assert::assertEqualsWithDelta($line['final_charge'], $balance['balance']['cost_with_tax'] ?? null, $this->epsilon, 'balance cost_with_tax must accumulate the tax-inclusive charge');
    }

    /**
     * Run a queue calculator the way the calculate cron action does:
     * getInstance (autoloads its queue lines) -> calc -> write -> removeFromQueue.
     */
    protected function runQueueCalculator(ApiTester $I, string $type): void
    {
        $I->resetBillrunSingletons();
        $calc = \Billrun_Calculator::getInstance(['type' => $type]);
        $calc->calc();
        $calc->write();
        $calc->removeFromQueue();
    }

    protected function getLineByFile(string $csvPath): array
    {
        $entity = \Billrun_Factory::db()->linesCollection()
            ->query(['file' => basename($csvPath)])
            ->cursor()->current();
        return $entity->isEmpty() ? [] : $entity->getRawData();
    }

    protected function getQueueRowByStamp(string $stamp): array
    {
        $entity = \Billrun_Factory::db()->queueCollection()
            ->query(['stamp' => $stamp])
            ->cursor()->current();
        return $entity->isEmpty() ? [] : $entity->getRawData();
    }

    /**
     * Fetch the subscriber's balance of a specific billing cycle - balance
     * documents are cycle-scoped by their from/to bounds, so the cycle start
     * of the given billrun key must fall inside them.
     *
     * A subscriber may hold several balance documents per cycle: the main
     * (default) balance carrying cost/cost_with_tax totals, and one balance
     * per service carrying that service's group usage. Pass $serviceName to
     * get a service balance; without it the main balance is returned.
     */
    protected function getBalanceBySid(int $sid, string $billrunKey, ?string $serviceName = null): array
    {
        $entity = \Billrun_Factory::db()->balancesCollection()
            ->query([
                'sid'          => $sid,
                'from'         => new \Mongodloid_Date(\Billrun_Billingcycle::getStartTime($billrunKey)),
                'to'           => new \Mongodloid_Date(\Billrun_Billingcycle::getEndTime($billrunKey)),
                'service_name' => is_null($serviceName) ? ['$exists' => false] : $serviceName,
            ])
            ->cursor()->current();
        return $entity->isEmpty() ? [] : $entity->getRawData();
    }

    // =========================================================================
    // DATA HELPERS
    // =========================================================================

    /**
     * Create account, plan, rate and subscriber so the input processor can
     * resolve the CDR row through customer + rate identification before the
     * filter runs. Each $*Details array is merged over the entity's defaults,
     * so tests can override/add fields (e.g. subscriber services).
     */
    protected function createBaseData(ApiTester $I, array $accountDetails = [], array $planDetails = [], array $rateDetails = [], array $subscriberDetails = []): void
    {
        $I->createAccountWithAllMandatoryCustomFields(array_merge([
            'firstname' => 'skip-calc-acc',
        ], $accountDetails));
        $this->accountDetails = json_decode($I->grabResponse(), true)['entity'];

        $I->generatePlan(array_merge([
            'name' => 'TEST_PLAN_SKIP_' . (int) (microtime(true) * 10000),
            'from' => '2025-01-01',
        ], $planDetails));
        $this->planDetails = json_decode($I->grabResponse(), true)['entity'];

        $I->generateRate(array_merge([
            'tariff_category' => 'retail',
            'key'             => 'CALL_SKIP',
            'rates' => [
                'call' => [
                    'BASE' => [
                        'rate' => [[
                            'from'        => 0,
                            'to'          => 'UNLIMITED',
                            'interval'    => 1,
                            'price'       => 1,
                            'uom_display' => ['range' => 'seconds', 'interval' => 'seconds'],
                        ]],
                    ],
                ],
            ],
        ], $rateDetails));
        $this->rateDetails = json_decode($I->grabResponse(), true)['entity'];

        $I->generateSubscriber(array_merge([
            'from'      => '2025-01-01',
            'firstname' => '0531234567',
            'aid'       => $this->accountDetails['aid'],
            'plan'      => $this->planDetails['name'],
            'services'  => [],
        ], $subscriberDetails));
        $this->subscriberDetails = json_decode($I->grabResponse(), true)['entity'];
    }

    /**
     * Single-element file_types payload accepted by $I->setSettings('file_types', ...).
     * The filter forces pricing to be skipped for any row whose uf.rate is CALL_SKIP.
     */
    protected function inputProcessor(): array
    {
        return [
            'file_type' => 'skipPricingTest',
            'parser'    => [
                'type'       => 'separator',
                'line_types' => ['H' => '/^none$/', 'D' => '//', 'T' => '/^none$/'],
                'separator'  => ',',
                'structure'  => [
                    ['name' => 'firstname', 'checked' => true],
                    ['name' => 'date',      'checked' => true],
                    ['name' => 'rate',      'checked' => true],
                    ['name' => 'volume',    'checked' => true],
                ],
                'csv_has_header' => true,
                'csv_has_footer' => false,
            ],
            'processor' => [
                'type'               => 'Usage',
                'date_field'         => 'date',
                'default_usaget'     => 'call',
                'default_unit'       => 'seconds',
                'default_volume_src' => ['volume'],
            ],
            'customer_identification_fields' => [
                'call' => [[
                    'target_key'  => 'firstname',
                    'src_key'     => 'firstname',
                    'conditions'  => [['field' => 'usaget', 'regex' => '/.*/']],
                    'clear_regex' => '//',
                ]],
            ],
            'rate_calculators' => [
                'retail' => [
                    'call' => [[[
                        'type'     => 'match',
                        'rate_key' => 'key',
                        'line_key' => 'rate',
                    ]]],
                ],
            ],
            'pricing' => ['call' => []],
            'unify'   => [],
            'filters' => [[
                'conditions' => [[
                    'field_name' => 'uf.rate',
                    'op'         => '$regex',
                    'value'      => 'CALL_SKIP',
                ]],
                'skip_calc' => ['pricing'],
            ]],
            'enabled'  => true,
            'receiver' => [
                'type'        => 'ftp',
                'connections' => [[
                    'receiver_type'    => 'ftp',
                    'passive'          => false,
                    'delete_received'  => false,
                    'user'             => 'admin',
                    'password'         => 'unused',
                    'host'             => '127.0.0.1',
                    'name'             => 'unused',
                    'remote_directory' => '/unused',
                ]],
            ],
        ];
    }
}
