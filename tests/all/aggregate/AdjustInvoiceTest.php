<?php

class AdjustInvoiceTest extends \Codeception\Test\Unit
{
    /**
     * @var \UnitTester
     */
    protected $tester;
    static $plan_name = 'PLAN_ADJUST_INVOICE';
    static $rate_name = 'RATE_ADJUST_INVOICE';

    protected function _before()
    {
        ini_set('error_reporting', E_ALL & ~E_WARNING & ~E_NOTICE);
        // cleanDB removes the plans and the rates as well - they are created again for every test
        $this->tester->cleanDB();
        $this->tester->generatePlan([
            'name' =>  self::$plan_name,
            "price" => [
                [
                    "price" => 100,
                    "from" => 0,
                    "to" => "UNLIMITED"
                ]
            ]
        ]);
               $this->tester->generateRate([
            'tariff_category' => 'retail',
            'key' =>  self::$rate_name,
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
    }

    protected function _after()
    {
        $this->tester->disableWorker();
    }

    
    public $defaultOptions = array(
        "type" => "customer",
        "stamp" => "202410",
        "page" => 0,
        "size" => 100,
        'fetchonly' => true,
        'generate_pdf' => 0,
        "force_accounts" => array(123)
    );

    protected $epsilon = 0.00001;
    public function testAdjustInvoiceAfterFinishCycle()
    {   
        
        $this->tester->createAccountWithAllMandatoryCustomFields();
        $account = json_decode($this->tester->grabResponse(), true)['entity'];
        $aid = $account['aid'];
        $this->tester->generateSubscriber(
            [
                'aid' => $aid,
                'plan' => self::$plan_name
    
            ]
        );
        $this->defaultOptions['force_accounts'] = $aid;
        $this->defaultOptions["stamp"] = "202610";
        $this->tester->runCycle($this->defaultOptions);

        $billrun_before = $this->tester->grabFromCollection('billrun', array('billrun_key' => '202610', 'aid' => $aid));
        $this->assertEquals(100, $billrun_before['totals']['before_vat'], '', $this->epsilon);
        $this->tester->enableWorker();
        $response = $this->tester->sendAdjustInvoice([
            'aid' => $aid,
            'billrun_key' => '202610',
            'credit_lines' => [[
                'aid' => $aid,
                'sid' => 0,
                'rate' => self::$rate_name,
                'credit_time' => '2026-09-03T11:41:14.279Z',
                'aprice' => '10',
                'usagev' => 1
            ]]
        ]);
        $this->assertEquals(1, $response['status'] ?? null, 'adjust invoice request failed: ' . json_encode($response));
        // the API only schedules the invoice regeneration (a Cycle_Account job) - execute it
        $this->assertEquals(1, $this->tester->runWorkerJobs(['generate_pdf' => 0]), 'expected one invoice regeneration job');
        $billrun_after = $this->tester->grabFromCollection('billrun', array('billrun_key' => '202610', 'aid' => $aid));
        $this->assertEquals(110, $billrun_after['totals']['before_vat'], '', $this->epsilon);
    }

    /**
     * several credit lines in one request - an account level charge, a subscriber
     * level charge multiplied by the volume, a refund and a charge that is priced
     * by the rate - and a second adjustment that is added on top of the first one
     */
    public function testAdjustInvoiceWithSeveralCreditLines()
    {
        list($aid, $sid) = $this->createAccountWithSubscriber();
        $this->runAccountCycle($aid);
        $this->tester->enableWorker();

        $response = $this->tester->sendAdjustInvoice([
            'aid' => $aid,
            'billrun_key' => '202610',
            'credit_lines' => [
                $this->creditLine($aid), // account level charge: +10
                $this->creditLine($aid, ['sid' => $sid, 'aprice' => '5', 'usagev' => 3]), // subscriber level charge: 5 * 3 = +15
                $this->creditLine($aid, ['aprice' => '-20']), // refund: -20
                // no price - priced by the rate: 30 seconds * 1 = +30
                ['aid' => $aid, 'sid' => $sid, 'rate' => self::$rate_name, 'credit_time' => '2026-09-03T11:41:14.279Z', 'usagev' => 30, 'credit_type' => 'charge'],
            ]
        ]);
        $this->assertEquals(1, $response['status'] ?? null, 'adjust invoice request failed: ' . json_encode($response));
        $this->assertCount(4, $response['stamps'], 'expected a stamp per credit line');

        // until the job runs the old invoice is removed and the lines wait with the billrun key
        $this->tester->dontSeeInCollection('billrun', ['billrun_key' => '202610', 'aid' => $aid]);
        $this->tester->seeNumElementsInCollection('lines', 4, ['aid' => $aid, 'type' => 'credit', 'billrun' => '202610', 'stamp' => ['$in' => $response['stamps']]]);
        $this->tester->seeNumElementsInCollection('lines', 1, ['aid' => $aid, 'type' => 'credit', 'sid' => $sid, 'usaget' => 'charge', 'aprice' => 15]);
        $this->tester->seeNumElementsInCollection('lines', 1, ['aid' => $aid, 'type' => 'credit', 'sid' => 0, 'usaget' => 'refund', 'aprice' => -20]);
        $this->tester->seeNumElementsInCollection('lines', 1, ['aid' => $aid, 'type' => 'credit', 'sid' => $sid, 'usaget' => 'charge', 'aprice' => 30]);

        $this->assertEquals(1, $this->tester->runWorkerJobs(['generate_pdf' => 0]), 'expected one invoice regeneration job');
        $billrun_after = $this->tester->grabFromCollection('billrun', array('billrun_key' => '202610', 'aid' => $aid));
        $this->assertEquals(135, $billrun_after['totals']['before_vat'], '', $this->epsilon);

        // a second adjustment keeps the lines of the first one
        $response = $this->tester->sendAdjustInvoice([
            'aid' => $aid,
            'billrun_key' => '202610',
            'credit_lines' => [$this->creditLine($aid, ['aprice' => '7'])]
        ]);
        $this->assertEquals(1, $response['status'] ?? null, 'second adjust invoice request failed: ' . json_encode($response));
        $this->assertEquals(1, $this->tester->runWorkerJobs(['generate_pdf' => 0]), 'expected one invoice regeneration job');
        $this->tester->seeNumElementsInCollection('lines', 5, ['aid' => $aid, 'type' => 'credit', 'billrun' => '202610']);
        $billrun_after = $this->tester->grabFromCollection('billrun', array('billrun_key' => '202610', 'aid' => $aid));
        $this->assertEquals(142, $billrun_after['totals']['before_vat'], '', $this->epsilon);
    }

    /**
     * every illegal request is refused with its own error, and none of them
     * touches the invoice, stores a credit line or schedules a job
     */
    public function testAdjustInvoiceRejectedRequests()
    {
        list($aid, $sid) = $this->createAccountWithSubscriber();
        $this->runAccountCycle($aid);
        $billrun_before = $this->tester->grabFromCollection('billrun', array('billrun_key' => '202610', 'aid' => $aid));
        $validRequest = [
            'aid' => $aid,
            'billrun_key' => '202610',
            'credit_lines' => [$this->creditLine($aid)]
        ];

        // the worker is off - even a legal request is refused
        $this->tester->disableWorker();
        $this->assertAdjustInvoiceRejected($validRequest, 'Workers are disabled');

        $this->tester->enableWorker();
        $rejectedRequests = [
            'Illegal aid' => ['aid' => 'abc'],
            'Illegal billrun key' => ['billrun_key' => '2026'],
            'Account 99999999 not found' => ['aid' => 99999999],
            'Invoice of billrun 202609 does not exist' => ['billrun_key' => '202609'],
            'Please supply at least one credit line' => ['credit_lines' => []],
            'Credit line aid does not match the account aid' => ['credit_lines' => [$this->creditLine($aid + 1)]],
            'is after the end of billrun 202610' => ['credit_lines' => [$this->creditLine($aid, ['credit_time' => '2026-10-03T11:41:14.279Z'])]],
            'Installments are not supported' => ['credit_lines' => [$this->creditLine($aid, ['installments' => 2])]],
            "Rate doesn't exist" => ['credit_lines' => [$this->creditLine($aid, ['rate' => 'NO_SUCH_RATE'])]],
            // one bad line refuses the whole request, the legal line included
            'Credit line aid does not match' => ['credit_lines' => [$this->creditLine($aid), $this->creditLine($aid + 1)]],
        ];
        foreach ($rejectedRequests as $expectedError => $override) {
            $this->assertAdjustInvoiceRejected(array_merge($validRequest, $override), $expectedError);
        }

        $this->tester->seeNumElementsInCollection('lines', 0, ['type' => 'credit']);
        $this->assertEquals(0, $this->tester->runWorkerJobs(), 'a rejected request must not schedule a job');
        $billrun_after = $this->tester->grabFromCollection('billrun', array('billrun_key' => '202610', 'aid' => $aid));
        $this->assertEquals($billrun_before['invoice_id'], $billrun_after['invoice_id'], 'the invoice was regenerated');
        $this->assertEquals(100, $billrun_after['totals']['before_vat'], '', $this->epsilon);
    }

    /**
     * the invoice can be adjusted only between the end of its cycle and its
     * confirmation: not before the cycle ran, not while it runs and not once confirmed
     */
    public function testAdjustInvoiceRejectedByInvoiceState()
    {
        list($aid, $sid) = $this->createAccountWithSubscriber();
        $request = [
            'aid' => $aid,
            'billrun_key' => '202610',
            'credit_lines' => [$this->creditLine($aid)]
        ];
        $this->tester->enableWorker();

        // the cycle did not run yet - there is no invoice to adjust
        $this->assertAdjustInvoiceRejected($request, 'Invoice of billrun 202610 does not exist');

        // the cycle is running - one of its pages did not finish yet
        $this->runAccountCycle($aid);
        $runningPage = ['billrun_key' => '202610', 'page_number' => 7, 'page_size' => 100, 'start_time' => new \MongoDB\BSON\UTCDateTime()];
        $this->tester->haveInCollection('billing_cycle', $runningPage);
        $this->assertAdjustInvoiceRejected($request, 'Billing cycle 202610 is currently running');
        \Billrun_Factory::db()->billing_cycleCollection()->remove(['billrun_key' => '202610', 'page_number' => 7]);

        // the invoice is confirmed
        $billrun = $this->tester->grabFromCollection('billrun', array('billrun_key' => '202610', 'aid' => $aid));
        $this->tester->confirmInvoices(['stamp' => '202610', 'invoices' => $billrun['invoice_id']]);
        $this->tester->seeInCollection('billrun', ['billrun_key' => '202610', 'aid' => $aid, 'billed' => 1]);
        $this->assertAdjustInvoiceRejected($request, 'Invoice of billrun 202610 is already confirmed');

        $this->tester->seeNumElementsInCollection('lines', 0, ['type' => 'credit']);
        $this->assertEquals(0, $this->tester->runWorkerJobs(), 'a rejected request must not schedule a job');
        $billrun_after = $this->tester->grabFromCollection('billrun', array('billrun_key' => '202610', 'aid' => $aid));
        $this->assertEquals($billrun['invoice_id'], $billrun_after['invoice_id'], 'the invoice was regenerated');
        $this->assertEquals(100, $billrun_after['totals']['before_vat'], '', $this->epsilon);
    }

    /**
     * @return array [aid, sid] of a new account with one subscriber on the 100 plan
     */
    protected function createAccountWithSubscriber()
    {
        $this->tester->createAccountWithAllMandatoryCustomFields();
        $aid = json_decode($this->tester->grabResponse(), true)['entity']['aid'];
        $this->tester->generateSubscriber(['aid' => $aid, 'plan' => self::$plan_name]);
        $sid = json_decode($this->tester->grabResponse(), true)['entity']['sid'];
        $this->assertNotEmpty($aid, 'the account was not created');
        $this->assertNotEmpty($sid, 'the subscriber was not created');
        return [$aid, $sid];
    }

    protected function runAccountCycle($aid)
    {
        $this->defaultOptions['force_accounts'] = $aid;
        $this->defaultOptions['stamp'] = '202610';
        $this->tester->runCycle($this->defaultOptions);
        $billrun = $this->tester->grabFromCollection('billrun', array('billrun_key' => '202610', 'aid' => $aid));
        $this->assertEquals(100, $billrun['totals']['before_vat'], '', $this->epsilon);
    }

    protected function creditLine($aid, array $override = [])
    {
        return array_merge([
            'aid' => $aid,
            'sid' => 0,
            'rate' => self::$rate_name,
            'credit_time' => '2026-09-03T11:41:14.279Z',
            'aprice' => '10',
            'usagev' => 1
        ], $override);
    }

    protected function assertAdjustInvoiceRejected($request, $expectedError)
    {
        $response = $this->tester->sendAdjustInvoice($request);
        $this->assertEquals(0, $response['status'] ?? null, "the request was not rejected ($expectedError): " . json_encode($response));
        $this->assertStringContainsString($expectedError, $response['message'] ?? '', 'wrong rejection reason: ' . json_encode($response));
    }



    
}
