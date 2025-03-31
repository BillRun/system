<?php

use function PHPUnit\Framework\assertEquals;

class billapiBalanceCest
{

    public $accountDetails;
    public $planDetails;
    public $serviceDetails;
    public $subscriberDetails;
    public $rateDetails;

    public static $isIPSet = false;
     public function _before(ApiTester $I)
    {
        if (!self::$isIPSet) {
          $this->setUP($I);
            self::$isIPSet = true;
        }
    }
    
    public $inputProcessor = [
        "file_type"=> "realTime",
        "type"=> "realtime",
        "parser"=>
          [
            "type"=> "json",
            "line_types"=> [ "H"=> "/^none$/", "D"=> "//", "T"=> "/^none$/" ],
            "separator"=> "",
            "structure"=>
              [
                [ "name"=> "sid", "checked"=> true ],
                [ "name"=> "date", "checked"=> true ],
                [ "name"=> "usage", "checked"=> true ],
                [ "name"=> "rate", "checked"=> true ],
                [ "name"=> "volume", "checked"=> true ],
              ],
            "csv_has_header"=> false,
            "csv_has_footer"=> false,
          ],
        "processor"=>
          [
            "type"=> "Realtime",
            "date_field"=> "date",
            "default_usaget"=> "call",
            "default_unit"=> "seconds",
            "default_volume_src"=> ["volume"],
          ],
        "customer_identification_fields"=>
          [
            "call"=>
              [
                [
                  "target_key"=> "sid",
                  "src_key"=> "sid",
                  "conditions"=> [[ "field"=> "usaget", "regex"=> "/.*/" ]],
                  "clear_regex"=> "//",
                ],
              ],
          ],
        "rate_calculators"=>
          [
            "retail"=>
              [
                "call"=>
                  [[[ "type"=> "match", "rate_key"=> "key", "line_key"=> "rate" ]]],
              ],
          ],
        "pricing"=> [ "call"=> [] ],
        "unify"=> [],
        "enabled"=> true,
        "filters"=> [],
        "realtime"=> [ "postpay_charge"=> true ],
        "response"=>
          [
            "encode"=> "json",
            "fields"=>
              [
                [
                  "response_field_name"=> "requestNum",
                  "row_field_name"=> "request_num",
                ],
                [
                  "response_field_name"=> "requestType",
                  "row_field_name"=> "request_type",
                ],
                [
                  "response_field_name"=> "sessionId",
                  "row_field_name"=> "session_id",
                ],
                [
                  "response_field_name"=> "returnCode",
                  "row_field_name"=> "granted_return_code",
                ],
                [ "response_field_name"=> "sid", "row_field_name"=> "sid" ],
                [
                  "response_field_name"=> "grantedVolume",
                  "row_field_name"=> "usagev",
                ],
              ],
          ],
        ];
    protected function setUP(ApiTester $I, $inputProcessor = null)
    {
        $inputProcessor = $inputProcessor ?: $this->inputProcessor;
        $I->setSettings('file_types',$inputProcessor);
        $type = [
        
          [
                  "usage_type"=> "call",
                  "label"=> "call",
                  "property_type"=> "time",
                  "invoice_uom"=> "seconds",
                  "input_uom"=> "seconds"
          ]
        ]; 
       $I->setSettings('usage_types',$type);
    }

    protected function createData(ApiTester $I , $accountDetails = [], $planDetails = [], $serviceDetails = [],$subscriberDetails = [],$rateDetails = [])
    {
        $I->createAccountWithAllMandatoryCustomFields(array_merge(['firstname' => 'yossi_test'],$accountDetails));
        $this->accountDetails = json_decode($I->grabResponse(), true)['entity'];
        $I->generatePlan(array_merge(['name' => 'TEST_PLAN_2'.microtime(true)*10000],$planDetails));
        $this->planDetails = json_decode($I->grabResponse(), true)['entity'];
        $I->generateService(array_merge(['name' => 'TEST_SERVICE'.microtime(true)*10000],$serviceDetails));
        $this->serviceDetails = json_decode($I->grabResponse(), true)['entity'];
        $I->generateSubscriber(array_merge(
          [
              'aid' => $this->accountDetails['aid'],
              'plan' => $this->planDetails['name']
          ],$subscriberDetails)
      );
        $this->subscriberDetails = json_decode($I->grabResponse(), true)['entity'];   
        $I->generateRate(array_merge(['key' => microtime(true)*10000],$rateDetails));
        $this->rateDetails = json_decode($I->grabResponse(), true)['entity'];
    }

    public function testA(ApiTester $I): void
    {
        $this->createData($I);
        $I->generateSubscriber(
            [
                'firstname' => 'yossi_test',
                'aid' => $this->accountDetails['aid'],
                'plan' => $this->planDetails['name']
            ]
        );
     
    }


    // public function testSubscriberPermanentchangePush(ApiTester $I)
    // {
    //     $this->createData($I);
    //     $I->generateSubscriber(
    //         [
    //             'firstname' => 'yossi_test',
    //             'aid' => $this->accountDetails['aid'],
    //             'plan' => $this->planDetails['name']
    //         ]
    //     );
    //     $this->subscriberDetails = json_decode($I->grabResponse(), true)['entity'];
    //     $effective_date = $from = date('Y-m-d H:i:s', strtotime('+1 day'));
    //     $options = [
    //         "push_fields" => [
    //             [
    //                 "field_name" => "services",
    //                 "field_values" => [
    //                     [
    //                         "name" => $this->serviceDetails['name'],
    //                         "from" => $from
    //                     ]
    //                 ]
    //             ]
    //         ]
    //     ];
    //     $query =["type"=>"subscriber","sid"=>$this->subscriberDetails['sid'],"effective_date"=> $effective_date];
    //     $update = ['from'=> $from];
    //     $I->sendBillapiPermanentchange('subscribers',$query,$update,$options);
    //     $a = $I->grabResponse();
    //     //in permanentchange cases the validation only the status is 1 until resolved BRCD-4744
    //     $I->seeResponseContains('{"status":1'); 
    
        
    // }
    // public function testCloseSubscriber(ApiTester $I)
    // {
    //     $this->createData($I);
    //     $I->generateSubscriber(
    //         [
    //             'firstname' => 'yossi_test',
    //             'aid' => $this->accountDetails['aid'],
    //             'plan' => $this->planDetails['name']
    //         ]
    //     );
    //     $this->subscriberDetails = json_decode($I->grabResponse(), true)['entity'];
    //     $I->sendBillapiClose('subscribers',['_id'=>$this->subscriberDetails['_id']['$id']],['to'=>date('Y-m-d', strtotime('+1 day'))]);
    //     $I->seeResponseIsJson();
    //     $I->seeResponseContains('{"status":1');
    //     //check the close date
    //     $a = $I->grabDataFromResponseByJsonPath('$.entity.to.sec');
    //     $I->assertEquals($a[0],strtotime(date('Y-m-d', strtotime('+1 day'))));
    // }
  
    // public function testReopenSubscriber(ApiTester $I)
    // {
    //     $this->createData($I);
    //     $I->generateSubscriber(
    //         [
    //             'from'=>'2024-01-01',
    //             'to'=>'2025-01-01',
    //             'firstname' => 'yossi_test',
    //             'aid' => $this->accountDetails['aid'],
    //             'plan' => $this->planDetails['name']
    //         ]
    //     );
    //     $this->subscriberDetails = json_decode($I->grabResponse(), true)['entity'];
    //     $I->sendBillapiReopen('subscribers',['_id'=>$this->subscriberDetails['_id']['$id']],['from'=>date('Y-m-d', strtotime('+1 year'))]);
    //     $I->seeResponseIsJson();
    //     $I->seeResponseContains('{"status":1');
    //     //check the reopen date
    //     $a = $I->grabDataFromResponseByJsonPath('$.entity.from.sec');
    //     $I->assertEquals($a[0],strtotime(date('Y-m-d', strtotime('+1 year'))));
    // }


    // public function testUniquegetSubscriber(ApiTester $I)
    // {
    //     $this->createData($I);
    //     $I->generateSubscriber(
    //         [
    //             'from'=>'2024-01-01',
    //             'firstname' => 'yossi_test',
    //             'aid' => $this->accountDetails['aid'],
    //             'plan' => $this->planDetails['name']
    //         ]
    //     );
    //     $this->subscriberDetails = json_decode($I->grabResponse(), true)['entity'];
    //     $I->sendBillapiUniqueget(['sid'=>$this->subscriberDetails['sid'],'aid'=>$this->accountDetails['aid']],'subscribers');
    //     $I->seeResponseIsJson();
    //     $I->seeResponseContains('{"status":1');
    //         $I->seeResponseContainsJson([
    //             'firstname' => 'yossi_test',
    //             'aid' => $this->accountDetails['aid'],
    //             'sid' => $this->subscriberDetails['sid'],
    //             'plan' => $this->planDetails['name'],
    //             'services' => []
    //         ]);
        
    // }

    // public function tesUpdateSubscriber(ApiTester $I)
    // {
    //     $this->createData($I);
    //     $I->generateSubscriber(
    //         [
    //             'from'=>'2024-01-01',
    //             'firstname' => 'barkuni',
    //             'aid' => $this->accountDetails['aid'],
    //             'plan' => $this->planDetails['name']
    //         ]
    //     );
    //     $this->subscriberDetails = json_decode($I->grabResponse(), true)['entity'];
    //     $I->sendBillapiUpdate('subscribers',['_id'=>$this->subscriberDetails['_id']['$id']],['firstname'=>'eviatar']);
    //     $I->seeResponseIsJson();
    //     $I->seeResponseContains('{"status":1');
    //     $I->seeResponseContainsJson(['firstname' => 'eviatar']);       
    // }


}
