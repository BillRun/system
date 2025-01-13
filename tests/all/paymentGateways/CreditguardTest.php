<?php

class CreditguardTest extends \Codeception\Test\Unit
{
    /**
     * @var \UnitTester
     */
    protected $tester;

    protected function _before()
    {
        ini_set('error_reporting', E_ALL & ~E_WARNING & ~E_NOTICE);
        // $this->tester->enableCreditGuardPGWithSettings();
    }

    protected function _after()
    {
    }

    public function testEnable()
    {
        $this->tester->enableCreditGuardPGWithSettings();
    }
    public function testSingelPaymentWithoutTokenize()
    {
        $this->tester->createAccountWithAllMandatoryCustomFields([
            "payment_gateway" => [
                "active" => [
                    "auth_number" => "9433084",
                    "transaction_exhausted" => true,
                    "card_type" => "00",
                    "card_token" => "1022273188555888",
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
            ]
        ]);
        $account = json_decode($this->tester->grabResponse(), true)['entity'];
        $this->tester->payApi(['aid' => $account['aid'], 'amount' => 300, 'dir' => 'tc']);
        $this->tester->getRequest(['aid' => (int) $account['aid'], 'amount' => 300]);
        $this->tester->iframe(['aid' => (int) $account['aid'], 'txid' => 300]);

        //test that the token NOT changed
        $this->tester->seeInCollection(
            'subscribers',
            [
                'aid' => (int) $account['aid'],
                'to'=> ['$gt' => new MongoDB\BSON\UTCDateTime(time() * 1000)],
                'payment_gateway.active.card_token' => "1022273188555888"
            ]
        );
          //test that the recept 
          $this->tester->seeInCollection(
            'bills',
            [
                'aid' => (int) $account['aid'],
                'amount' => 300,
                "gateway_details.action" => "SinglePayment",
                "gateway_details.transferred_amount" => 300,
                "gateway_details.transaction_status" => "000",
              //  'paid'=>['$in'=>['true',true,1,'1']],
                "pending" => false
            ]);

        //test that the bill paid
        $this->tester->seeInCollection(
            'bills',
            [
                'aid' => (int) $account['aid'],
                'amount' => 300,
                'paid'=>['$in'=>['true',true,1,'1']]
          
            ]);
    }
    public function testSingelPaymentWithTokenize()
    {
        $this->tester->createAccountWithAllMandatoryCustomFields([
            "payment_gateway" => [
                "active" => [
                    "auth_number" => "9433084",
                    "transaction_exhausted" => true,
                    "card_type" => "00",
                    "card_token" => "1022273188555888",
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
            ]
        ]);
        $account = json_decode($this->tester->grabResponse(), true)['entity'];
        $this->tester->payApi(['aid' => $account['aid'], 'amount' => 400, 'dir' => 'tc']);
        $this->tester->getRequest(['aid' => (int) $account['aid'], 'amount' => 400, "tokenize_on_single_payment" => true]);
        $this->tester->iframe(['aid' => (int) $account['aid'], 'txid' => 400]);

        //test that the token changed
        $this->tester->seeInCollection(
            'subscribers',
            [
                'aid' => (int) $account['aid'],
                'to'=> ['$gt' => new MongoDB\BSON\UTCDateTime(time() * 1000)],
                'payment_gateway.active.card_token' => "1022273188555607"
            ]
        );

        //test that the recept 
        $this->tester->seeInCollection(
            'bills',
            [
                'aid' => (int) $account['aid'],
                'amount' => 400,
                "gateway_details.action" => "SinglePaymentToken",
                "gateway_details.transferred_amount" => 400,
                "gateway_details.transaction_status" => "000",
              //  'paid'=>['$in'=>['true',true,1,'1']],
                "pending" => false
            ]);
        //test the bill
        $this->tester->seeInCollection(
            'bills',
            [
                'aid' => (int) $account['aid'],
                'amount' => 400,
                'paid'=>['$in'=>['true',true,1,'1']]
            ]);
    }

    public function testSingelPaymentErorr()
    {
        $this->tester->createAccountWithAllMandatoryCustomFields([
            "payment_gateway" => [
                "active" => [
                    "auth_number" => "9433084",
                    "transaction_exhausted" => true,
                    "card_type" => "00",
                    "card_token" => "1022273188555888",
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
            ]
        ]);
        $account = json_decode($this->tester->grabResponse(), true)['entity'];
        $this->tester->payApi(['aid' => $account['aid'], 'amount' => 400000000000000000000000000, 'dir' => 'tc']);
        $this->tester->getRequest(['aid' => (int) $account['aid'], 'amount' => 400000000000000000000000000, "tokenize_on_single_payment" => true]);
        $this->tester->seeResponseContainsJson([
            'status' => 0,
            'details' => [
                'message' => "Can't Create Transaction"
            ]
        ]);
    }

}