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
    }

    /**
     * BRCD-5250 bug reproduction - a line stuck in the queue after the customer
     * calculator (queue calc_name=customer) is later completed by running the
     * rate and pricing calculators cron-style (calc -> write -> removeFromQueue),
     * with the production queue chain (customer, rate, pricing, tax).
     *
     * Since the tax calculator was merged into the pricing calculator
     * (updateRow -> applyTaxToRow), once pricing ran the line is fully
     * calculated (aprice/billrun/tax_data/final_charge), so its queue entry
     * must be removed right after pricing.
     *
     * Currently FAILS on the last assertion: the line is updated but its queue
     * entry is removed only when the tax calculator also runs.
     *
     * The stuck state is created through the real pipeline: the CDR references
     * rate CALL_STUCK which does not exist yet, so the rate calculator fails
     * during ingestion and the queue row stays at calc_name=customer.
     */
    public function testStuckQueueLineRemovedAfterRateAndPricing(ApiTester $I): void
    {
        $this->createBaseData($I);

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
            $stamp = $line['stamp'];

            $queueRow = $this->getQueueRowByStamp($stamp);
            $I->assertEquals('customer', $queueRow['calc_name'] ?? null, 'line must be stuck in the queue after the customer calculator');

            // the missing rate now exists, so the cron calculators can finish the line
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

            $this->runQueueCalculator($I, 'rate_Usage');
            $queueRow = $this->getQueueRowByStamp($stamp);
            $I->assertEquals('rate', $queueRow['calc_name'] ?? null, 'rate calculator must advance the stuck line to calc_name=rate');

            $this->runQueueCalculator($I, 'customerPricing');

            $line = $this->getLineByFile($csvPath);
            $I->assertArrayHasKey('aprice',       $line, 'pricing calculator must price the stuck line');
            $I->assertArrayHasKey('billrun',      $line, 'pricing calculator must set billrun on the stuck line');
            $I->assertArrayHasKey('tax_data',     $line, 'pricing calculator must apply tax to the stuck line (tax is merged into pricing)');
            $I->assertArrayHasKey('final_charge', $line, 'pricing calculator must set final_charge on the stuck line');

            $queueRow = $this->getQueueRowByStamp($stamp);
            \PHPUnit\Framework\Assert::assertEmpty(
                $queueRow,
                'BRCD-5250: the line must be removed from the queue right after the pricing calculator (which already applies tax) - a separate tax calculator run must not be required'
            );
        } finally {
            \Billrun_Factory::config()->setConfigValue('queue.calculators', $originalCalculators);
        }
    }

    // =========================================================================
    // CALCULATOR HELPERS
    // =========================================================================

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

    // =========================================================================
    // DATA HELPERS
    // =========================================================================

    /**
     * Create account, plan, rate and subscriber so the input processor can
     * resolve the CDR row through customer + rate identification before the
     * filter runs.
     */
    protected function createBaseData(ApiTester $I): void
    {
        $I->createAccountWithAllMandatoryCustomFields(['firstname' => 'skip-calc-acc']);
        $this->accountDetails = json_decode($I->grabResponse(), true)['entity'];

        $I->generatePlan(['name' => 'TEST_PLAN_SKIP_' . (int) (microtime(true) * 10000), 'from' => '2025-01-01']);
        $this->planDetails = json_decode($I->grabResponse(), true)['entity'];

        $I->generateRate([
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
        ]);
        $this->rateDetails = json_decode($I->grabResponse(), true)['entity'];

        $I->generateSubscriber([
            'from'      => '2025-01-01',
            'firstname' => '0531234567',
            'aid'       => $this->accountDetails['aid'],
            'plan'      => $this->planDetails['name'],
            'services'  => [],
        ]);
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
