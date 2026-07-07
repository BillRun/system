<?php

/**
 * BRCD-5328 — last_bills attached to the billrun document during cycle aggregation.
 */
class LastBillsTest extends \Codeception\Test\Unit
{
    /**
     * @var \UnitTester
     */
    protected $tester;

    protected $epsilon = 0.00001;

    public $defaultOptions = array(
        "type" => "customer",
        "stamp" => "202410",
        "page" => 0,
        "size" => 100,
        'fetchonly' => true,
        'generate_pdf' => 0,
        "force_accounts" => array(),
    );

    protected function _before()
    {
        $this->tester->cleanDB();
    }

    protected function _after()
    {
    }

    /**
     * Generate a plan + account + subscriber for the test.
     */
    protected function createTestData()
    {
        $this->tester->generatePlan([
            'name' => 'LAST_BILLS_PLAN_1',
            'price' => [['price' => 5, 'from' => 0, 'to' => 'UNLIMITED']],
        ]);
        $plan = json_decode($this->tester->grabResponse(), true)['entity'];
        $this->tester->createAccountWithAllMandatoryCustomFields([]);
        $account = json_decode($this->tester->grabResponse(), true)['entity'];
        $this->tester->generateSubscriber(['aid' => $account['aid'], 'plan' => $plan['name']]);
        $subscriber = json_decode($this->tester->grabResponse(), true)['entity'];
        return [
            'plan' => $plan,
            'account' => $account,
            'subscriber' => $subscriber,
        ];
    }

    /**
     * Seed 3 prior valid bills (payments) with positive due (dir=tc) at known urts.
     */
    protected function seedPriors($aid)
    {
        $this->tester->payApi(['aid' => $aid, 'amount' => 10, 'dir' => 'tc', 'urt' => '2024-04-01T00:00:00.000Z']);
        $this->tester->payApi(['aid' => $aid, 'amount' => 20, 'dir' => 'tc', 'urt' => '2024-05-01T00:00:00.000Z']);
        $this->tester->payApi(['aid' => $aid, 'amount' => 30, 'dir' => 'tc', 'urt' => '2024-06-01T00:00:00.000Z']);
    }

    protected function assertLastBillsShape($billrun)
    {
        $this->assertArrayHasKey('last_bills', $billrun, 'last_bills should be attached when condition matches');
        $this->assertCount(4, $billrun['last_bills'], '1 synthetic current + 3 priors');

        $entries = $billrun['last_bills'];

        // Entry 0 is the synthetic current bill.
        $this->assertEquals('inv', $entries[0]['type']);
        $this->assertArrayHasKey('balance', $entries[0]);
        $this->assertArrayHasKey('due', $entries[0]);

        // Priors 1..3 are sorted urt descending: 30, 20, 10.
        $this->assertEqualsWithDelta(30, $entries[1]['due'], $this->epsilon);
        $this->assertEqualsWithDelta(20, $entries[2]['due'], $this->epsilon);
        $this->assertEqualsWithDelta(10, $entries[3]['due'], $this->epsilon);

        // Running balance invariant: balance[i] === balance[i+1] + due[i].
        for ($i = 0; $i < count($entries) - 1; $i++) {
            $this->assertEqualsWithDelta(
                $entries[$i + 1]['balance'] + $entries[$i]['due'],
                $entries[$i]['balance'],
                $this->epsilon,
                "Running balance invariant failed at entry $i"
            );
        }

        // The newest prior's balance equals priors-only total (60), by construction of the helper.
        $this->assertEqualsWithDelta(60, $entries[1]['balance'], $this->epsilon);

        // The synthetic current's balance = priors-total + current.due.
        $this->assertEqualsWithDelta(60 + $entries[0]['due'], $entries[0]['balance'], $this->epsilon);
    }

    /**
     * Regular invoice via runCycle → last_bills attached + running balance math is correct.
     */
    public function testLastBillsAttachedForRegularInvoice()
    {
        // Regular invoices don't set attributes.invoice_type, so match "not immediate" (which
        // also matches the field-missing case in Mongo $ne semantics).
        \Billrun_Factory::config()->setConfigValue('billrun.last_bills', [
            [
                'conditions' => [
                    ['field' => 'attributes.invoice_type', 'op' => '$ne', 'value' => 'immediate'],
                ],
                'count' => 3,
            ],
        ]);

        $data = $this->createTestData();
        $aid = $data['account']['aid'];
        $this->seedPriors($aid);

        $this->defaultOptions['force_accounts'] = [$aid];
        $this->tester->runCycle($this->defaultOptions);

        $billrun = $this->tester->grabFromCollection('billrun', [
            'billrun_key' => $this->defaultOptions['stamp'],
            'aid' => $aid,
        ]);
        $this->assertLastBillsShape($billrun);
    }

    /**
     * Immediate invoice via sendOnetimeInvoiceApi → last_bills attached + running balance math is correct.
     * Mirrors onetimeInvoiceCest's setup (plan + rate + CDR).
     */
    public function testLastBillsAttachedForImmediateInvoice()
    {
        $this->tester->overrideConfigValue('billrun.last_bills', [
            [
                'conditions' => [
                    ['field' => 'attributes.invoice_type', 'op' => '$eq', 'value' => 'immediate'],
                ],
                'count' => 3,
            ],
        ]);

        $data = $this->createTestData();
        $aid = $data['account']['aid'];
        $this->seedPriors($aid);

        $rateKey = 'LAST_BILLS_IMMEDIATE_RATE_1';
        $this->tester->generateRate([
            'tariff_category' => 'retail',
            'key' => $rateKey,
            'rates' => [
                'call' => [
                    'BASE' => [
                        'rate' => [
                            [
                                'from' => 0,
                                'to' => 'UNLIMITED',
                                'interval' => 1,
                                'price' => 1,
                                'uom_display' => ['range' => 'seconds', 'interval' => 'seconds'],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $cdr = [
            'aid' => $aid,
            'rate' => $rateKey,
            'aprice' => 5,
            'sid' => 0,
            'usagev' => 1,
            'type' => 'credit',
            'credit_time' => date('Y-m-d\TH:i:s.v\Z'),
        ];
        $this->tester->sendOnetimeInvoiceApi([$cdr], $aid, ['send_email' => 0, 'step' => 0]);

        $billrun = $this->tester->grabFromCollection('billrun', [
            'aid' => $aid,
            'attributes.invoice_type' => 'immediate',
        ]);
        $this->assertLastBillsShape($billrun);
    }

    /**
     * No matching rule for this invoice_type → last_bills must not appear on the doc.
     */
    public function testLastBillsNotAttachedWhenConditionDoesNotMatch()
    {
        \Billrun_Factory::config()->setConfigValue('billrun.last_bills', [
            [
                'conditions' => [
                    ['field' => 'attributes.invoice_type', 'op' => '$eq', 'value' => 'immediate'],
                ],
                'count' => 3,
            ],
        ]);

        // runCycle produces a regular invoice — the immediate-only rule won't match.
        $data = $this->createTestData();
        $aid = $data['account']['aid'];
        $this->seedPriors($aid);

        $this->defaultOptions['force_accounts'] = [$aid];
        $this->tester->runCycle($this->defaultOptions);

        $billrun = $this->tester->grabFromCollection('billrun', [
            'billrun_key' => $this->defaultOptions['stamp'],
            'aid' => $aid,
        ]);
        $this->assertNotEmpty($billrun, 'billrun document should exist for the account');
        $this->assertArrayNotHasKey('last_bills', $billrun, 'last_bills should be absent when no condition matches');
    }
}
