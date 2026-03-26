<?php

class billapiAccountCest
{

    public $accountDetails;
    public $planDetails;
    public $serviceDetails;
    protected $isRun =false;
    	private $defaultTimezone;

    public function _before(ApiTester $I)
    {
        	if(!$this->isRun) {
			$this->isRun = true;
			//load the  config so  we  can  ovverride the  timezone AFTER  it  waas  set by configuration
			Billrun_Factory::config();
			$this->defaultTimezone = date_default_timezone_get();
			date_default_timezone_set('Asia/Jerusalem');
		}
    }



    public function testCreateAccount(ApiTester $I)
    {
        $I->createAccountWithAllMandatoryCustomFields([
            "firstname"=> "rgf",
            "lastname"=> "yhtr",
            "email"=> "gresw@gmail.com",
            "country"=> "israel",
            "address"=> "fgd",
            "zip_code"=> "hntr",
            "invoice_shipping_method"=> "email",
            "payment_gateway"=> [
                "former"=> [],
                "active"=> [
                    "name"=> "masav",
                    "bank_code"=> "12",
                    "bank_branch_num"=> "45326",
                    "account_num"=> 543,
                    "customer_id"=> "324",
                    "four_digits"=> "435",
                    "card_expiration"=> "444"
                ]
            ],
        ]);
        $I->seeResponseIsJson();
        $I->seeResponseContains('{"status":1');
            $this->accountDetails = json_decode($I->grabResponse(), true)['entity'];
        $I->seeResponseContainsJson(['entity'=>[
            "firstname"=> "rgf",
            "lastname"=> "yhtr",
            "email"=> "gresw@gmail.com",
            "country"=> "israel",
            "address"=> "fgd",
            "zip_code"=> "hntr",
            "invoice_shipping_method"=> "email"
        ]]);
        $I->verifyCollectionRecord(
            'subscribers',
            [
                'aid' => $this->accountDetails['aid'],
                'email' => "gresw@gmail.com"
            ]);
        $this->accountDetails = json_decode($I->grabResponse(), true)['entity'];
    }


    public function testAccountPermanentchange(ApiTester $I)
    {
        $I->createAccountWithAllMandatoryCustomFields([
            "payment_gateway"=> [
                "former"=> [],
                "active"=> [
                    "name"=> "masav",
                    "bank_code"=> "12",
                    "bank_branch_num"=> "45326",
                    "account_num"=> 543,
                    "customer_id"=> "324",
                    "four_digits"=> "435",
                    "card_expiration"=> "444"
                ]
            ],
        ]);
        $this->accountDetails = json_decode($I->grabResponse(), true)['entity'];

        $effective_date = $from = date('Y-m-d H:i:s', strtotime('+1 day'));  
        $query =["type"=>"account","aid"=>$this->accountDetails['aid'],"effective_date"=> $effective_date];
        $update = ['from'=> $from, "payment_gateway"=> [
            "former"=> [],
            "active"=> [
                "name"=> "masav",
                "bank_code"=> "123",
                "bank_branch_num"=> "321",
                "account_num"=> 897,
                "customer_id"=> "563",
                "four_digits"=> "46535",
                "card_expiration"=> "1255"
            ]
        ]];
        $I->sendBillapiPermanentchange('accounts',$query,$update);
        //in permanentchange cases the validation only the status is 1 until resolved BRCD-4744
        $I->seeResponseContains('{"status":1'); 
        //check the account details before the change
        $I->verifyCollectionRecordWithDates(
            'subscribers',
            [
                'aid' => $this->accountDetails['aid'],
                'payment_gateway.active.bank_code' => "12",
                'to' => $effective_date
            ]
        );
        //check the account details after the change 
        $I->verifyCollectionRecordWithDates(
            'subscribers',
            [
                'aid' => $this->accountDetails['aid'],
                'payment_gateway.active.bank_code' => "123",
                'from' => $effective_date
            ]
        );
        
    }



    public function testAccountBillapiUniqueget(ApiTester $I)
   {
    $from = date('Y-m-d H:i:s', strtotime('-10 day'));

    $I->createAccountWithAllMandatoryCustomFields([
        "from" => $from,
        "payment_gateway"=> [
            "former"=> [],
            "active"=> [
                "name"=> "masav",
                "bank_code"=> "12",
                "bank_branch_num"=> "45326",
                "account_num"=> 543,
                "customer_id"=> "324",
                "four_digits"=> "435",
                "card_expiration"=> "444"
            ]
        ],
    ]);
    $this->accountDetails = json_decode($I->grabResponse(), true)['entity'];
    
    // permanentchange  changed bank code
    $effective_date = $from = date('Y-m-d H:i:s', strtotime('-2 day'));
    $query = ["type"=>"account", "aid"=>$this->accountDetails['aid'], "effective_date"=> $effective_date];
    $update = ['from'=> $from, "payment_gateway"=> [
        "former"=> [],
        "active"=> [
            "name"=> "masav",
            "bank_code"=> "123",
            "bank_branch_num"=> "321",
            "account_num"=> 897,
            "customer_id"=> "563",
            "four_digits"=> "46535",
            "card_expiration"=> "1255"
        ]
    ]];
    $I->sendBillapiPermanentchange('accounts', $query, $update);
    $I->seeResponseContains('{"status":1');
    
    // Verify both records exist in the database
    $I->verifyCollectionRecordWithDates(
        'subscribers',
        [
            'aid' => $this->accountDetails['aid'],
            'payment_gateway.active.bank_code' => "12",
        ]
    );
    $I->verifyCollectionRecordWithDates(
        'subscribers',
        [
            'aid' => $this->accountDetails['aid'],
            'payment_gateway.active.bank_code' => "123",
        ]
    );
    
   
    //uniqueget should return the currect account record (with "bank_code" => "123" )
    $future_date = date('Y-m-d H:i:s', strtotime('+2 days')); // After the effective date
    $I->sendBillapiUniqueget([
        "aid" => $this->accountDetails['aid'],
    ], 'accounts');
    $I->seeResponseContainsJson([
        "aid" => $this->accountDetails['aid'],
        "payment_gateway" => [
            "active" => [
                "bank_code" => "123" 
            ]
        ]
    ]);
    $I->seeResponseContains('"status":1');
    date_default_timezone_set($this->defaultTimezone );

}

}
