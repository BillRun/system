<?php
namespace Helper;

// here you can define custom actions
// all public methods declared in helper class will be available in $I
use Codeception\Module\REST;

class  RealTimeApiHelper extends BillRunAPI{


    public function sendInitialRequestCdr($fileType, $request)
    {
        $request['requestType'] = 1; // Set requestType to 1 for initial

        return $this->sendRealTimeRequest($fileType, $request);
    }

    public function sendUpdateRequestCdr($fileType, $request)
    {
        $request['requestType'] = 2; // Set requestType to 2 for update

        return $this->sendRealTimeRequest($fileType, $request);
    }

    public function sendFinalRequestCdr($fileType, $request)
    {
        $request['requestType'] = 3; // Set requestType to 3 for final

        return $this->sendRealTimeRequest($fileType, $request);
    }


        /**
     * Assert that the realtime API response contains the expected granted volume.
     *
     * @param int|float|string $expectedGrantedVolume
     * @param string $jsonPath JSONPath used to locate granted volume in response
     */
    public function assertGrantedVolume($expectedGrantedVolume, $jsonPath = '$..grantedVolume')
    {
        /** @var REST $rest */
        $rest = $this->getModule('REST');
        $values = $rest->grabDataFromResponseByJsonPath($jsonPath);

        \PHPUnit\Framework\Assert::assertNotEmpty(
            $values,
            "No grantedVolume field found in response (jsonPath: {$jsonPath})"
        );

        \PHPUnit\Framework\Assert::assertEquals(
            $expectedGrantedVolume,
            $values[0],
            "grantedVolume does not match expected value"
        );
    }

    
    
}
