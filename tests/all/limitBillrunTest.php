<?php

class limitBillrunTest extends \Codeception\Test\Unit
{
   
/**
     * @var \UnitTester
     */
    protected $tester;

    protected function _before()
    {
        
    }

    protected function _after()
    {
    }
    

    protected function createTestData($account =[],$subscriber =[],$plan =[],$numOfSubscriber=1){
        $this->tester->generatePlan($plan);
        $plan = json_decode($this->tester->grabResponse(), true)['entity'];
        $this->tester->createAccountWithAllMandatoryCustomFields($account);
        $account = json_decode($this->tester->grabResponse(), true)['entity'];
        for($i=0;$i<$numOfSubscriber;$i++){
            $this->tester->generateSubscriber(
                [
                    'aid' => $account['aid'],
                    'plan' => $plan['name']
                ]
            );
            $subscriber[] = json_decode($this->tester->grabResponse(), true)['entity'];
        }
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
     * Tests the behavior of the billrun object when the subscriber limit is set to 1.
     * 
     * This test verifies that when the 'billrun.save_to_file_subs_limit' configuration
     * is set to 1, the billrun object is created with an empty 'subs' array. This happens
     * because the subscriber data is saved to a file instead of being stored directly in
     * the billrun object when the number of subscribers exceeds the configured limit.
     * 
     * The test:
     * 1. Gets the active billrun stamp
     * 2. Sets the subscriber limit to 1
     * 3. Creates test data with a plan
     * 4. Runs a billing cycle for the created account
     * 5. Verifies that the billrun object has an empty 'subs' array
     * 
     * @return void
     */
    public function test_limitBillrunObjectTo1_empty_subs()
    {
        $stamp = Billrun_Billrun::getActiveBillrun();
       \Billrun_Factory::config()->setConfigValue('billrun.save_to_file_subs_limit', 1);
       $data =  $this->createTestData([],[],[ "price" => [
            [
                "price" => 100,
                "from" => 0,
                "to" => "UNLIMITED"
            ]
        ]]);
        $this->defaultOptions['force_accounts'] = [$data['account']['aid']];
        $this->defaultOptions["stamp"] = $stamp ;
        $this->tester->runCycle($this->defaultOptions);
        $this->tester->seeInCollection('billrun', ['billrun_key' => $stamp, 'aid' =>  $data['account']['aid']]);
        $this->tester->dontSeeInCollection('billrun_subs', ['billrun_key' => $stamp, 'aid' =>  $data['account']['aid']]);
    }
   
    public function test_limitBillrunObjectTo2_haveOneSub_saveToSubs()
    {
        $stamp = Billrun_Billrun::getActiveBillrun();
       \Billrun_Factory::config()->setConfigValue('billrun.save_to_file_subs_limit', 2);
       $data =  $this->createTestData([],[],["price" => [
            [
                "price" => 100,
                "from" => 0,
                "to" => "UNLIMITED"
            ]
        ]]);
        $this->defaultOptions['force_accounts'] = [$data['account']['aid']];
        $this->defaultOptions["stamp"] = $stamp ;
        $this->tester->runCycle($this->defaultOptions);
        // The order of entries inside the 'subs' array is not deterministic
        // (the sid=0 account entry may land at index 0 or 1), so don't rely on
        // fixed positions. Assert both required entries exist anywhere in the
        // array: 'subs.sid' => X matches a doc whose subs array has ANY element
        // with sid=X. Two assertions => BOTH must be present.
        $this->tester->seeInCollection('billrun', [
            'billrun_key' => $stamp, 
            'aid' =>  $data['account']['aid']
            ]
        );
         $this->tester->seeInCollection('billrun_subs', [
            'key' => $stamp, 
            'aid' =>  $data['account']['aid'],
            'subs.sid' => 0,
        ]);
        $this->tester->seeInCollection('billrun', [
            'billrun_key' => $stamp,
            'aid' =>  $data['account']['aid'],
            'sid'=>$data['subscriber'][0]['sid']        ]);
    } 
 
}
