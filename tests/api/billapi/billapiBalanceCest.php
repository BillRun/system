<?php

use function PHPUnit\Framework\assertEquals;

class billapiBalanceCest
{

    public $accountDetails;
    public $planDetails;
    public $serviceDetails;
    public $subscriberDetails;
    public $rateDetails;
    public $defaultTimezone;

    public static $isIPSet = false;
     public function _before(ApiTester $I)
    {
        if (!self::$isIPSet) {
          $this->setUP($I);
            self::$isIPSet = true;
            Billrun_Factory::config();
            $this->defaultTimezone = date_default_timezone_get();
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
        $I->generateSubscriber(array_merge(
          [
              'aid' => $this->accountDetails['aid'],
              'plan' => $this->planDetails['name']
          ],$subscriberDetails)
      );
        $this->subscriberDetails = json_decode($I->grabResponse(), true)['entity'];   
        $I->generateRate(array_merge(['tariff_category'=>'retail','key' => microtime(true)*10000],$rateDetails));
        $this->rateDetails = json_decode($I->grabResponse(), true)['entity'];
    }
    public function testGetUsageBalance(ApiTester $I): void
    {
        $this->createData($I);
        
        // First real-time request
        $request = [
            'sid' => $this->subscriberDetails['sid'],
            'date' => date('Y-m-d H:i:s'),
            'usage' => 1,
            'rate' => (string)$this->rateDetails['key'],
            'volume' => 1
        ];
        $I->sendRealTimeRequest('realTime', $request);
        
        // Second real-time request (35 days later)
        $request = [
            'sid' => $this->subscriberDetails['sid'],
            'date' => date('Y-m-d H:i:s', strtotime('+35 days')),
            'usage' => 1,
            'rate' => (string)$this->rateDetails['key'],
            'volume' => 1
        ];
        $I->sendRealTimeRequest('realTime', $request);
        
        // Get specific balance by billapi get
        $currentTimestamp = date('Y-m-d H:i:s');
        $I->sendBillapiGet([
            'aid' => $this->accountDetails['aid'],
            'sid' => $this->subscriberDetails['sid'],
            'from' => ['$lte' => $currentTimestamp],
            'to' => ['$gt' => $currentTimestamp]
        ], 'balances');
   
        // Check response staus
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson([
            'status' => 1
        ]);
        
        $timezone = new DateTimeZone($this->defaultTimezone);
        $expectedFromDate = (new DateTime('first day of this month 00:00:00', $timezone))
            ->format('Y-m-01\T00:00:00O'); // First day of current month (local TZ)
        $expectedToDate = (new DateTime('first day of next month 00:00:00', $timezone))
            ->format('Y-m-01\T00:00:00O'); // First day of next month (local TZ)
        
        // Check that get the correct balance
        $I->seeResponseContainsJson([
            'details' => [
                [
                    'aid' => $this->accountDetails['aid'],
                    'sid' => $this->subscriberDetails['sid'],
                    'balance' => [
                        'cost' => 11,
                        'totals' => [
                            'call' => [
                                'cost' => 11,
                                'count' => 1,
                                'usagev' => 1,
                                'out_group' => [
                                    'usagev' => 1
                                ]
                            ]
                        ]
                    ],
                    'connection_type' => 'postpaid',
                    'plan_description' => $this->planDetails['name'],
                    'from' => $expectedFromDate,
                    'to' => $expectedToDate
                ]
            ]
        ]);
        // Check the details array length is 1 ,  validate the response contains only one balance object
        $details = json_decode($I->grabResponse(), true)['details'];
        $I->assertCount(1, $details);

    }
   
}
