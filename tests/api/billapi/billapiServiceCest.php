<?php


class billapiServiceCest
{

    public $serviceDetails;

    public function _before(ApiTester $I)
    {
    }
    


    protected function createData(ApiTester $I , $serviceDetails = [])
    {
        $I->generateService(array_merge(['name' => 'TEST_SERVICE_'.microtime(true)*10000],$serviceDetails), true);
        $this->serviceDetails = json_decode($I->grabResponse(), true)['entity'];
    }


    public function testUniquegetServiceByName(ApiTester $I)
    {
        $this->createData($I);
        $I->sendBillapiUniqueget(['name'=>$this->serviceDetails['name']],'services');
        $I->seeResponseIsJson();
        $I->seeResponseContains('{"status":1');
        $I->seeResponseContainsJson([
                'name' => $this->serviceDetails['name'],
       ]);
        
    }

    /**
     * Test case for uniqueget with specific service names and date range
     */
    public function testUniquegetWithSpecificNamesAndDateRange(ApiTester $I)
    {
        // Create the services 
        $time = microtime(true) * 10000;
        $serviceNames = [
            'CALLS_3000'.$time,
            'SMS_3000'.$time,
            'DATA_150GB'.$time
        ];
        foreach ($serviceNames as $serviceName) {
            $this->createData($I, ['name' => $serviceName,'description'=>$serviceName."_old",'from'=>'2021-01-01']);
        }
        //update the service
        foreach ($serviceNames as $serviceName) {
            $I->sendBillapiCloseandnew('services', ['name' => $serviceName,'effective_date'=>'2024-01-01'],['description'=>$serviceName.'_new','from'=>'2024-05-01',
            "recurrence" =>[
		"periodicity" => "month"]]);
        }
        
     
        $currentTime = date('Y-m-d H:i:s');
        // Prepare the query for uniqueget request with specific service names and date range.
        $query = [
            'from' => ['$lte' => $currentTime],
            'to' => ['$gte' => $currentTime],
            'name' => ['$in' => $serviceNames]
        ];
        
        // Send the uniqueget request
        $I->sendBillapiUniqueget($query, 'services');
        $a = $I->grabResponse();
        // Verify response
        $I->seeResponseIsJson();
        $I->seeResponseContains('{"status":1');
        
        // Check that all services are returned, and that the old services are not returned
        foreach ($serviceNames as $serviceName) {
            $I->seeResponseContainsJson(['description' => $serviceName.'_new']);
            $I->dontSeeResponseContainsJson(['name' => $serviceName."_old"]);
        }
    }
}




