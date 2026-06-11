<?php

use Codeception\Lib\ModuleContainer;
use Codeception\Module\Cli;

class collectTest extends \Codeception\Test\Unit
{
    /**
     * @var \UnitTester
     */
    protected $tester;
    private $cli;
    public $defaultWithoutRejectionSettings = [
        'conditions' => [
            'customers' => [
                [
                    'field' => 'aid',
                    'op' => 'exists',
                    'value' => false
                ]
            ]
        ]
    ];
    public $defaultWithRejectionSettings = [
        'conditions' => [
            'customers' => [
                [
                   'field' => 'payment_gateway', //this is hack should be payment_gateway.active but need to add it to custom field 
                    'op' => 'exists',
                    'value' => true
                ]
            ]
        ]
        
    ];
    public $defaultCollectionProcesses = [
        [
            "name" => "condition_process",
            "label" => "Condition process",
            "conditions" => [
                [
                    "account" => [
                        "fields" => [
                            [
                                "field" => "country",
                                "op" => "in",
                                "value" => [
                                    "ISREAL"
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            "settings" => [
                "min_debt" => 10,
                "change_state_url" => "",
                "change_state_method" => "post"
            ],
            "steps" => [
                [
                    "type" => "mail",
                    "active" => true,
                    "do_after_days" => 10,
                    "name" => "MAIL",
                    "id" => "7e234258-6ba7-437f-b6de-5328bcbed534"
                ]
            ]
        ],
        [
            "name" => "default_process",
            "label" => "Default process",
            "conditions" => [
                [
                    "account" => [
                        "fields" => []
                    ]
                ]
            ],
            "settings" => [
                "min_debt" => 1,
                "change_state_url" => "",
                "change_state_method" => "post"
            ],
            "steps" => [
                [
                    "type" => "http",
                    "active" => true,
                    "name" => "HTTP",
                    "do_after_days" => 5,
                    "content" => [
                        "url" => "http://localhost:8074/index.html#/"
                    ],
                    "id" => "561c7d57-9822-4d26-b919-4c2cf56ee2d2"
                ]
            ]
        ]
    ];


    protected function _before() {
        $this->tester->cleanDB();
        $this->initCollectionConfig($this->defaultCollectionProcesses, $this->defaultWithoutRejectionSettings);
        $moduleContainer = new ModuleContainer(new \Codeception\Lib\Di(), []);
        $this->cli = new Cli($moduleContainer);

    }


    protected function _after()
    {
    }
    
    public function testCollectWithAidAndWithoutRejectionRquired_1()
    {
        $this->tester->createAccountWithAllMandatoryCustomFields();
        $account = json_decode($this->tester->grabResponse(), true)['entity'];
        $aid = $account['aid'];
        $payment = [
            "amount"=>10,
            "aid"=>$aid,
            "dir"=>"tc",
        ];
        $options['aids'] = $aid;
        $this->tester->dontSeeInCollection('subscribers', ['in_collection' => true, 'aid' =>  $aid, 'type'=>"account"]);
        $this->tester->payApi($payment);
        $this->sendCollectCommand($options);
        //in collection
        $this->tester->seeInCollection('subscribers', ['in_collection' => true, 'aid' =>  $aid, 'type'=>"account"]);
        $this->tester->seeInCollection('collection_steps', ['process_name' => 'default_process', 'extra_params.aid' =>  $aid, 'step_type'=>"http"]);
        $payment['dir'] = 'fc';
        $this->tester->payApi($payment);
        // $this->sendCollectCommand($options);
        //out collection
        $this->tester->dontSeeInCollection('collection_steps', ['process_name' => 'default_process', 'extra_params.aid' =>  $aid, 'step_type'=>"http"]);
        $lastAccount = $this->tester->grabFromCollection('subscribers', [
                'aid' => $aid, 
                'type' => 'account'
            ], ['_id' => -1]);
        $this->tester->seeInCollection('subscribers', ['_id' => $lastAccount['_id'], 'in_collection' => ['$exists' => false], 'aid' =>  $aid, 'type'=>"account"]);
    }

    public function testCollectWithAidAndWithoutRejectionRquiredWithCondition_2()
    {
        $this->tester->createAccountWithAllMandatoryCustomFields(['country'=> 'ISREAL']);
        $account = json_decode($this->tester->grabResponse(), true)['entity'];
        $aid = $account['aid'];
        $payment = [
            "amount"=>12,
            "aid"=>$aid,
            "dir"=>"tc",
        ];
        $options['aids'] = $aid;
        $this->tester->dontSeeInCollection('subscribers', ['in_collection' => true, 'aid' =>  $aid, 'type'=>"account"]);
        $this->tester->payApi($payment);
        $this->sendCollectCommand($options);
        //in collection
        $this->tester->seeInCollection('subscribers', ['in_collection' => true, 'aid' =>  $aid, 'type'=>"account"]);
        $this->tester->seeInCollection('collection_steps', ['process_name' => 'condition_process', 'extra_params.aid' =>  $aid, 'step_type'=>"mail"]);
        $payment['dir'] = 'fc';
        $this->tester->payApi($payment);
        // $this->sendCollectCommand($options);
        //out collection
        $this->tester->dontSeeInCollection('collection_steps', ['process_name' => 'condition_process', 'extra_params.aid' =>  $aid, 'step_type'=>"mail"]);
        $this->tester->seeInCollection('subscribers', [ 'in_collection' => ['$exists' => false], 'aid' =>  $aid, 'type'=>"account", 'to' => ['$gt' => new \MongoDB\BSON\UTCDateTime(strtotime('2027-01-01'))]]);
    }

    public function testCollectWithAidAndWithoutRejectionRquiredWithConditionNotPassMinDebt()
    {
        $this->tester->createAccountWithAllMandatoryCustomFields(['country'=> 'ISREAL']);
        $account = json_decode($this->tester->grabResponse(), true)['entity'];
        $aid = $account['aid'];
        $payment = [
            "amount"=>5,
            "aid"=>$aid,
            "dir"=>"tc",
        ];
        $options['aids'] = $aid;
        $this->tester->dontSeeInCollection('subscribers', ['in_collection' => true, 'aid' =>  $aid, 'type'=>"account"]);
        $this->tester->payApi($payment);
        $this->sendCollectCommand($options);
        $this->tester->dontSeeInCollection('subscribers', ['in_collection' => true, 'aid' =>  $aid, 'type'=>"account"]);
        $this->tester->dontSeeInCollection('collection_steps', ['process_name' => 'condition_process', 'extra_params.aid' =>  $aid, 'step_type'=>"mail"]);

    }


    public function testCollectWithAidAndWithRejectionRquired()
    {
        $this->setWithRejectionRquired($this->defaultCollectionProcesses);
        $this->tester->createAccountWithAllMandatoryCustomFields(['payment_gateway' => ['active' => [ "name" => "CreditGuard"]]]);
        $account = json_decode($this->tester->grabResponse(), true)['entity'];
        $aid = $account['aid'];
        $payment = [
            "amount"=>10,
            "aid"=>$aid,
            "dir"=>"tc",
        ];
        $options['aids'] = $aid;
        $this->tester->dontSeeInCollection('subscribers', ['in_collection' => true, 'aid' =>  $aid, 'type'=>"account"]);
        $this->tester->payApi($payment);
        $this->sendCollectCommand($options);
        $this->tester->dontSeeInCollection('subscribers', ['in_collection' => true, 'aid' =>  $aid, 'type'=>"account"]);
        $this->tester->dontSeeInCollection('collection_steps', ['process_name' => 'default_process', 'extra_params.aid' =>  $aid, 'step_type'=>"http"]);

    }

    public function testCollectWithAidAndWithRejectionRquiredWithCondition()
    {
        $this->setWithRejectionRquired($this->defaultCollectionProcesses);

        $this->tester->createAccountWithAllMandatoryCustomFields(['country'=> 'ISREAL', 'payment_gateway' => ['active' => [ "name" => "CreditGuard"]]]);
        $account = json_decode($this->tester->grabResponse(), true)['entity'];
        $aid = $account['aid'];
        $payment = [
            "amount"=>12,
            "aid"=>$aid,
            "dir"=>"tc",
        ];
        $options['aids'] = $aid;
        $this->tester->dontSeeInCollection('subscribers', ['in_collection' => true, 'aid' =>  $aid, 'type'=>"account"]);
        $this->tester->payApi($payment);
        $this->sendCollectCommand($options);
        $this->tester->dontSeeInCollection('subscribers', ['in_collection' => true, 'aid' =>  $aid, 'type'=>"account"]);
        $this->tester->dontSeeInCollection('collection_steps', ['process_name' => 'condition_process', 'extra_params.aid' =>  $aid, 'step_type'=>"mail"]);

    }

    public function testCollectWithAidAndWithRejectionRquiredWithConditionNotPassMinDebt()
    {
        $this->setWithRejectionRquired($this->defaultCollectionProcesses);
        $this->tester->createAccountWithAllMandatoryCustomFields(['country'=> 'ISREAL', 'payment_gateway' => ['active' => [ "name" => "CreditGuard"]]]);
        $account = json_decode($this->tester->grabResponse(), true)['entity'];
        $aid = $account['aid'];
        $payment = [
            "amount"=>5,
            "aid"=>$aid,
            "dir"=>"tc",
        ];
        $options['aids'] = $aid;
        $this->tester->dontSeeInCollection('subscribers', ['in_collection' => true, 'aid' =>  $aid, 'type'=>"account"]);
        $this->tester->payApi($payment);
        $this->sendCollectCommand($options);
        $this->tester->dontSeeInCollection('subscribers', ['in_collection' => true, 'aid' =>  $aid, 'type'=>"account"]);
        $this->tester->dontSeeInCollection('collection_steps', ['process_name' => 'condition_process', 'extra_params.aid' =>  $aid, 'step_type'=>"mail"]);

    }

public function testCollectInCollectionWithoutAids()
    {
        $this->tester->createAccountWithAllMandatoryCustomFields();
        $account = json_decode($this->tester->grabResponse(), true)['entity'];
        $aid1 = $account['aid'];
        $payment = [
            "amount"=>10,
            "aid"=>$aid1,
            "dir"=>"tc",
        ];
        $this->tester->payApi($payment);
        $this->tester->createAccountWithAllMandatoryCustomFields(['country'=> 'ISREAL']);
        $account = json_decode($this->tester->grabResponse(), true)['entity'];
        $aid2 = $account['aid'];
        $payment = [
            "amount"=>12,
            "aid"=>$aid2,
            "dir"=>"tc",
        ];
        $this->tester->payApi($payment);
        $this->sendCollectCommand($options);
        //in collection
        $this->tester->seeInCollection('subscribers', ['in_collection' => true, 'aid' =>  $aid1, 'type'=>"account"]);
        $this->tester->seeInCollection('collection_steps', ['process_name' => 'default_process', 'extra_params.aid' =>  $aid1, 'step_type'=>"http"]);
        $this->tester->seeInCollection('subscribers', ['in_collection' => true, 'aid' =>  $aid2, 'type'=>"account"]);
        $this->tester->seeInCollection('collection_steps', ['process_name' => 'condition_process', 'extra_params.aid' =>  $aid2, 'step_type'=>"mail"]);

        
    }

    public function testCollectOutCollectionWithoutAids()
    {
         $this->tester->createAccountWithAllMandatoryCustomFields(['in_collection' => true]);
        $account = json_decode($this->tester->grabResponse(), true)['entity'];
        $aid1 = $account['aid'];
        $this->tester->createAccountWithAllMandatoryCustomFields(['country'=> 'ISREAL', 'in_collection' => true]);
        $account = json_decode($this->tester->grabResponse(), true)['entity'];
        $aid2 = $account['aid'];

        $this->sendCollectCommand($options);


        //out collection
        $this->tester->dontSeeInCollection('collection_steps', ['process_name' => 'condition_process', 'extra_params.aid' =>  $aid1, 'step_type'=>"http"]);
        $this->tester->dontSeeInCollection('collection_steps', ['process_name' => 'condition_process', 'extra_params.aid' =>  $aid2, 'step_type'=>"mail"]);

       
        $this->tester->seeInCollection('subscribers', [ 'in_collection' => ['$exists' => false], 'aid' =>  $aid1, 'type'=>"account", 'to' => ['$gt' => new \MongoDB\BSON\UTCDateTime(strtotime('2027-01-01'))]]);
        $this->tester->seeInCollection('subscribers', [ 'in_collection' => ['$exists' => false], 'aid' =>  $aid2, 'type'=>"account", 'to' => ['$gt' =>new \MongoDB\BSON\UTCDateTime(strtotime('2027-01-01'))]]);
    }



    protected function initCollectionConfig($collectionProcesses, $rejectionSettings){
        $this->tester->setSettings('collection', ['processes' => $collectionProcesses, 'settings' => [
            "run_on_holidays" => true,
            "run_on_days" => [
                true,
                true,
                true,
                true,
                true,
                true,
                true
            ],
            "run_on_hours" => [],
            "rejection_required" =>  $rejectionSettings
        ]]);
    }

    
    
    protected function setWithRejectionRquired($collectionProcesses){
        $model = new \ConfigModel();
        $model->updateConfig('collection', ['processes' => $collectionProcesses, 'settings' => [
            "run_on_holidays" => true,
            "run_on_days" => [
                true,
                true,
                true,
                true,
                true,
                true,
                true
            ],
            "run_on_hours" => [],
            "rejection_required" => $this->defaultWithRejectionSettings]]);
        \Billrun_Config::getInstance()->loadDbConfig();
    }
    
    
    protected function sendCollectCommand($options = []) {
        $command = 'php public/index.php --env container --collect';
        if (isset($options['aids'])) {
            $command .= " aids=" . $options['aids'];
        }
        $this->cli->runShellCommand($command);
        return $this->cli;
    }
}