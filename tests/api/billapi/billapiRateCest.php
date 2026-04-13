<?php

use function PHPUnit\Framework\assertEquals;

class billapiRateCest
{

   

    public function _before(ApiTester $I)
    {
        $data = [
            [
                "property_type" => "time",
                "invoice_uom" => "",
                "input_uom" => "",
                "usage_type" => "call",
                "label" => "call"
            ]
        ];
        $category = "usage_types";
        $I->setSettings($category, $data);
    }
    


    protected function createRate(ApiTester $I ,$rateDetails = [])
    {
        $I->generateRate(array_merge(['key' => microtime(true)*10000],$rateDetails));
        return json_decode($I->grabResponse(), true)['entity'];
        
    }

    public function testCreateRate(ApiTester $I)
    {
        $this->createRate($I,['description' => 'yossi_test_rate']);
        $I->seeResponseIsJson();
        $I->seeResponseContains('{"status":1');
        $I->seeResponseContainsJson([
            'description' => 'yossi_test_rate'
        ]);
    }


    public function testUniquegetRate(ApiTester $I)
    {
        $this->createRate($I,['description' => 'yossi_test_rate_1']);
        $rate2 =  $this->createRate($I,['description' => 'yossi_test_rate_2']);
       
        $I->sendBillapiUniqueget(['key'=>$rate2['key']],'rates');
        $I->seeResponseIsJson();
        $I->seeResponseContains('{"status":1');
        $I->seeResponseContainsJson([
            'description' => 'yossi_test_rate_2',
            'key' => $rate2['key']
            ]);
        
    }

}
