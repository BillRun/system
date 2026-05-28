<?php

class aggrgateInternalModeTest extends \Codeception\Test\Unit
{
    /**
     * @var \UnitTester
     */
    protected $tester;

    protected $epsilon = 0.00001;

    public $defaultOptions = array(
        "type" => "customer",
        "page" => 0,
        "size" => 100,
        'fetchonly' => true,
        'generate_pdf' => 0,
    );

    protected function _before()
    {
        ini_set('error_reporting', E_ALL & ~E_WARNING & ~E_NOTICE);
        $this->tester->enableDBModeSettings();
    }

    protected function _after()
    {
    }

    public function testDbCustomersAggregateSize()
    {
        $billruns =\Billrun_Factory::db()->billrunCollection();
        $billruns->remove(['_id'=>['$exists' => true]]);

        $this->tester->generatePlan();
        $plan = json_decode($this->tester->grabResponse(), true)['entity'];
        $this->tester->createAccountWithAllMandatoryCustomFields(["firstname" => "or1"]);
        $account = json_decode($this->tester->grabResponse(), true)['entity'];
        $aid1 = $account['aid'];
        $effective_date = $from = date('Y-m-d H:i:s', strtotime("2025-10-02"));  

        $query =["type"=>"account","aid"=> $aid1, "effective_date"=> $effective_date];
        $update = ['from'=> $from, "firstname" => "or2"];
        $this->tester->sendBillapiPermanentchange('accounts',$query,$update,$options);
        $effective_date = $from = date('Y-m-d H:i:s', strtotime("2025-10-21"));  

        $query =["type"=>"account","aid"=> $aid1, "effective_date"=> $effective_date];
        $update = ['from'=> $from, "firstname" => "or3"];
        $this->tester->sendBillapiPermanentchange('accounts',$query,$update,$options);
        $this->tester->generateSubscriber(
            [
                'aid' => $aid1,
                'plan' => $plan['name'],
    
            ]
        );
        $subscriber = json_decode($this->tester->grabResponse(), true)['entity'];

        $this->tester->createAccountWithAllMandatoryCustomFields(["firstname" => "or1"]);
        $account = json_decode($this->tester->grabResponse(), true)['entity'];
        $aid2 = $account['aid'];
        $effective_date = $from = date('Y-m-d H:i:s', strtotime('2025-10-02'));  

        $query =["type"=>"account","aid"=> $aid2, "effective_date"=> $effective_date];
        $update = ['from'=> $from, "firstname" => "or2"];
        $this->tester->sendBillapiPermanentchange('accounts',$query,$update,$options);
        $effective_date = $from = date('Y-m-d H:i:s', strtotime('2025-10-21'));  

        $query =["type"=>"account","aid"=> $aid2, "effective_date"=> $effective_date];
        $update = ['from'=> $from, "firstname" => "or3"];
        $this->tester->sendBillapiPermanentchange('accounts',$query,$update,$options);
        $this->tester->generateSubscriber(
            [
                'aid' => $aid2,
                'plan' => $plan['name'],
    
            ]
        );
        
        $stamp = "202511";
        $this->defaultOptions['force_accounts'] = [$aid1, $aid2];
        $this->defaultOptions["stamp"] = $stamp;
        $this->defaultOptions['size'] = 2;
        $this->tester->runCycle($this->defaultOptions);
        $this->tester->assertEquals(2, $this->tester->grabCollectionCount('billrun', [
            'billrun_key' => $stamp
        ]));
        $billruns =\Billrun_Factory::db()->billrunCollection();
        $billruns->remove(['_id'=>['$exists' => true]]);

        $this->defaultOptions['force_accounts'] = [$aid1, $aid2];
        $this->defaultOptions["stamp"] = $stamp;
        $this->defaultOptions['size'] = 1;
        $this->tester->runCycle($this->defaultOptions);
        $this->tester->assertEquals(1, $this->tester->grabCollectionCount('billrun', array('billrun_key' => $stamp)));
    }

        public function testAggregatePdfCreate()
    {
        $billruns =\Billrun_Factory::db()->billrunCollection();
        $billruns->remove(['_id'=>['$exists' => true]]);
        $this->tester->generatePlan();
        $plan = json_decode($this->tester->grabResponse(), true)['entity'];
        $this->tester->createAccountWithAllMandatoryCustomFields(["firstname" => "or1"]);
        $account = json_decode($this->tester->grabResponse(), true)['entity'];
        $aid1 = $account['aid'];
        $this->tester->generateSubscriber(
            [
                'aid' => $aid1,
                'plan' => $plan['name'],
    
            ]
        );
        $subscriber = json_decode($this->tester->grabResponse(), true)['entity'];
        $stamp = "202511";
        $this->defaultOptions['force_accounts'] = [$aid1];
        $this->defaultOptions["stamp"] = $stamp;
        $this->defaultOptions['generate_pdf'] = 1;
        $this->tester->runCycle($this->defaultOptions);

        $billrun = $this->tester->grabFromCollection('billrun', ['billrun_key' => $stamp, 'aid' => $aid1]);
        $this->tester->assertNotEmpty($billrun, "billrun document not found for aid={$aid1} and billrun_key={$stamp}");
        $this->tester->assertNotEmpty($billrun['invoice_file'] ?? null, "invoice_file path is empty on billrun document");
        $this->tester->assertFileExists($billrun['invoice_file'], "PDF file not created at path: " . ($billrun['invoice_file'] ?? ''));
    }

}