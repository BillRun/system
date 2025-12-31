<?php

class conditaionlChargeTest extends \Codeception\Test\Unit
{
   
/**
     * @var \UnitTester
     */
    protected $tester;

    protected function _before()
    {
        Billrun_Factory::config()->setInternalSubscribersMode();
    }

    protected function _after()
    {
    }
     

    protected function createTestData($account =[],$subscriber =[],$plan =[]){
       
        $this->tester->generatePlan($plan);
        $plan = json_decode($this->tester->grabResponse(), true)['entity'];
        $this->tester->createAccountWithAllMandatoryCustomFields($account);
        $account = json_decode($this->tester->grabResponse(), true)['entity'];
        $subscriber =  $this->tester->generateSubscriber(
            [
                'aid' => $account['aid'],
                'plan' => $plan['name']
            ]
        );
        $subscriber = json_decode($this->tester->grabResponse(), true)['entity'];
        return ['account'=>$account,'subscriber'=>$subscriber,'plan'=>$plan];
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
   
   
    /**
     * Tests the scenario where a subscriber does not match the charge condition.
     *
     * This function creates test data with a specific plan, runs a billing cycle,
     * and verifies that the correct charge is applied to the account.
     *
     * @return void
     */
    public function test_subscriberNotmutchChargeCondition()
    {
       $data =  $this->createTestData([],[],['name' => 'PLAN_'.microtime(true)*10000, "price" => [
            [
                "price" => 100,
                "from" => 0,
                "to" => "UNLIMITED"
            ]
        ]]);

        $this->tester->generateConditaionlCharge(["params"=> [
            "min_subscribers"=> "",
            "max_subscribers"=> "",
            "conditions"=> [
                [
                    "subscriber"=> [
                        [
                            "fields"=> [
                                [
                                    "field"=> "sid",
                                    "op"=> "nin",
                                    "value"=> [
                                        $data['subscriber']['sid']
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ]]);
        $charge = json_decode($this->tester->grabResponse(), true)['entity'];
        $this->defaultOptions['force_accounts'] = [$data['account']['aid']];
        $this->defaultOptions["stamp"] = "202501";
        $this->tester->runCycle($this->defaultOptions);
        $this->tester->removeCollectionRecord('charges', array('key' =>$charge['key'] ));
        $this->tester->verifyCollectionRecord('billrun', array('billrun_key' => '202501', 'aid' =>  $data['account']['aid'],'totals.before_vat'=>100));
    }
    
}
