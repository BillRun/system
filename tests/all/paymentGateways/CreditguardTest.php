<?php

class CreditguardTest extends \Codeception\Test\Unit
{
    /**
     * @var \UnitTester
     */
    protected $tester;
    
    protected function _before()
    {
      // $this->tester->enableCreditGuardPGWithSettings();
    }

    protected function _after()
    {
    }

    public function testEnable()
    {
        $this->tester->enableCreditGuardPGWithSettings();
    }
    public function testa()
    {
        $this->tester->createAccountWithAllMandatoryCustomFields([
            "payment_gateway" => [
                "active" => [
                    "auth_number" => "9433084",
                    "transaction_exhausted" => true,
                    "card_type" => "00",
                    "card_token" => "1022273188555606",
                    "name" => "CreditGuard",
                    "card_acquirer" => "1",
                    "instance_name" => "CreditGuard",
                    "credit_company" => "1",
                    "card_brand" => "1",
                    "personal_id" => "890108566",
                    "generate_token_time" => "2023-11-29T08:55:32Z",
                    "keepCCDetails" => null,
                    "card_expiration" => "1225",
                    "four_digits" => "5606"
                ]
            ]]);
        $account = json_decode($this->tester->grabResponse(), true)['entity'];
        $this->tester->payApi(['aid'=>$account['aid'], 'amount'=>10,'dir'=>'tc']);
        $a =  $this->tester->getRequest(['aid'=>(int)$account['aid'], 'amount'=>10]);
        $a = $a;

        $this->tester->iframe();

        //sent to iframe and check the response



    }
}