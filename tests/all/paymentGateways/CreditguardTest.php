<?php
use Helper\PaymentStatus;
use Helper\TestHelper;

class CreditguardTest extends \Codeception\Test\Unit
{
    /**
     * @var \UnitTester
     */
    protected $tester;
    protected $responseWrapper;
    
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

    //https://billrun.atlassian.net/browse/BRCD-4684
    //https://billrun.atlassian.net/browse/BRCD-4682
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
        $account = $this->tester->getEntity();
        $this->tester->payApi(['aid' => $account['aid'], 'amount' => 300, 'dir' => 'tc']);
        $this->tester->getRequest(['aid' => (int) $account['aid'], 'amount' => 300]);
        $this->tester->iframe(['aid' => (int) $account['aid'], 'txid' => 300]);

        //test that the token NOT changed
        $this->tester->verifyCollectionRecord(
            'subscribers',
            [
                'aid' => (int) $account['aid'],
                'to'=> ['$gt' => TestHelper::CurrentTime()],
                'payment_gateway.active.card_token' => "1022273188555888"
            ]

          
        );
          //test that the recept 
          $this->tester->verifyCollectionRecord(
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
        $this->tester->verifyCollectionRecord(
            'bills',
            [
                'aid' => (int) $account['aid'],
                'amount' => 300,
                'paid'=> ['$in'=>PaymentStatus::PAID]
          
            ]);
    }
    //https://billrun.atlassian.net/browse/BRCD-4684
    //https://billrun.atlassian.net/browse/BRCD-4682
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
                ],
            "tenant_return_url" => " http://web/paymentgateways/success"
        ]);
        $account = $this->tester->getEntity();
        $this->tester->payApi(['aid' => $account['aid'], 'amount' => 400, 'dir' => 'tc']);
        $this->tester->getRequest(['aid' => (int) $account['aid'], 'amount' => 400, "tokenize_on_single_payment" => true]);
        $this->tester->iframe(['aid' => (int) $account['aid'], 'txid' => 400]);

        //test that the token changed
        $this->tester->verifyCollectionRecord(
            'subscribers',
            [
                'aid' => (int) $account['aid'],
                'to'=> ['$gt' => TestHelper::CurrentTime()],
                'payment_gateway.active.card_token' => "1022273188555607"
            ]
        );

        //test that the recept 
        $this->tester->verifyCollectionRecord(
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
        $this->tester->verifyCollectionRecord(
            'bills',
            [
                'aid' => (int) $account['aid'],
                'amount' => 400,
                'paid'=> ['$in'=>PaymentStatus::PAID]
            ]);
    }
    //https://billrun.atlassian.net/browse/BRCD-4682
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
        $account = $this->tester->getEntity();
        $this->tester->payApi(['aid' => $account['aid'], 'amount' => 400000000000000000000000000, 'dir' => 'tc']);
        $this->tester->getRequest(['aid' => (int) $account['aid'], 'amount' => 400000000000000000000000000, "tokenize_on_single_payment" => true]);
        $this->tester->seeResponseContainsJson([
            'status' => 0,
            'details' => [
                'message' => "Can't Create Transaction"
            ]
        ]);
    }

    //https://billrun.atlassian.net/browse/BRCD-4684
    //https://billrun.atlassian.net/browse/BRCD-4683
    public function testChargeAccountAfterReTokenize()
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
        $account = $this->tester->getEntity();
        $this->tester->payApi(['aid' => $account['aid'], 'amount' => 400, 'dir' => 'tc']);

        //change payment without pay 
        $this->tester->getRequest(['aid' => (int) $account['aid'], 'type'=>'subscriber']);
        $this->tester->iframe(['aid' => (int) $account['aid'], 'txid' => 100]);

         //pay by charge API
        $this->tester->chargeAccountApi(['aids' => (int) $account['aid']]);

        //test that the charge is with the new payment methid 
        $this->tester->verifyCollectionRecord(
            'bills',
            [
                'aid' => (int) $account['aid'],
                'amount' => 400,
                "gateway_details.card_token" => "1022273188555606",
                "gateway_details.amount" => 400,
                "pending" => false
            ]);
   
    }

    //https://billrun.atlassian.net/browse/BRCD-4684
    //https://billrun.atlassian.net/browse/BRCD-4682
    public function testSingelPaymentWithInstallmentsWithoutTokenize()
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
        $account = $this->tester->getEntity();
        $this->tester->payApi(['aid' => $account['aid'], 'amount' => 500, 'dir' => 'tc']);
        $this->tester->getRequest(['aid' => (int) $account['aid'], 'amount' => 500, "installments"=>["number_of_payments"=>3]]);
        $this->tester->iframe(['aid' => (int) $account['aid'], 'txid' => 500]);

        //test that the token changed
        $this->tester->verifyCollectionRecord(
            'subscribers',
            [
                'aid' => (int) $account['aid'],
                'to'=> ['$gt' => TestHelper::CurrentTime()],
                'payment_gateway.active.card_token' => "1022273188555888"
            ]
        );

        //test that the recept 
        $this->tester->verifyCollectionRecord(
            'bills',
            [
                'aid' => (int) $account['aid'],
                'amount' => 500,
                "gateway_details.action" => "SinglePayment",
                "gateway_details.transferred_amount" => 500,
                "gateway_details.transaction_status" => "000",
                "pending" => false,
                "installments.total_amount" => 500,
                "installments.number_of_payments" =>3,
                "installments.first_payment" => 166.66,
                "installments.periodical_payment" => 1
                
            
            ]);
        //test the bill
        $this->tester->verifyCollectionRecord(
            'bills',
            [
                'aid' => (int) $account['aid'],
                'amount' => 500,
                'paid'=> ['$in'=>PaymentStatus::PAID]
            ]);
    }

    //https://billrun.atlassian.net/browse/BRCD-4682
    //https://billrun.atlassian.net/browse/BRCD-4684
    public function testSingelPaymentWithInstallmentsWithTokenize()
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
        $account = $this->tester->getEntity();
        $this->tester->payApi(['aid' => $account['aid'], 'amount' => 500, 'dir' => 'tc']);
        $this->tester->getRequest(['aid' => (int) $account['aid'], 'amount' => 500, "tokenize_on_single_payment" => true,"installments"=>["number_of_payments"=>3]]);
        $this->tester->iframe(['aid' => (int) $account['aid'], 'txid' => 500]);

        //test that the token changed
        $this->tester->verifyCollectionRecord(
            'subscribers',
            [
                'aid' => (int) $account['aid'],
                'to'=> ['$gt' => TestHelper::CurrentTime()],
                'payment_gateway.active.card_token' => "1022273188555607"
            ]
        );

        //test that the recept 
        $this->tester->verifyCollectionRecord(
            'bills',
            [
                'aid' => (int) $account['aid'],
                'amount' => 500,
                "gateway_details.action" => "SinglePaymentToken",
                "gateway_details.transferred_amount" => 500,
                "gateway_details.transaction_status" => "000",
                "pending" => false,
                "installments.total_amount" => 500,
                "installments.number_of_payments" =>3,
                "installments.first_payment" => 166.66,
                "installments.periodical_payment" => 1
                
            
            ]);
        //test the bill
        $this->tester->verifyCollectionRecord(
            'bills',
            [
                'aid' => (int) $account['aid'],
                'amount' => 500,
                'due' => 500,
                'paid'=> ['$in'=>PaymentStatus::PAID]
            ]);
        //verify that is only one bill
        $this->tester->verifyCollectionCount(
            'bills',
             1,
            [
               'aid' => (int) $account['aid'],
                'amount' => 500,
                'due' => 500,
                'paid'=> ['$in'=>PaymentStatus::PAID]
            ]);
    }


    //https://billrun.atlassian.net/browse/BRCD-4683
    public function testChargeAccountAidsFilter()
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
        $account1=$this->tester->getEntity();
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
        $account2=$this->tester->getEntity();
        $this->tester->payApi(['aid' => $account1['aid'], 'amount' => 400, 'dir' => 'tc']);
        $this->tester->payApi(['aid' => $account2['aid'], 'amount' => 500, 'dir' => 'tc']);
         //pay by charge API with aids filter with only account1
        $this->tester->chargeAccountApi(['aids' => (int) $account1['aid']]);

        //test the charge bill only for account1
        $this->tester->verifyCollectionRecord(
            'bills',
            [
                'aid' => (int) $account1['aid'],
                'amount' => 400,
                "gateway_details.card_token" => "1022273188555888",
                "gateway_details.amount" => 400,
                "pending" => false
            ]);

        $this->tester->verifyCollectionRecord(
            'bills',
            [
                'aid' => (int) $account2['aid'],
                'amount' => 500,
                "paid" => ['$in'=>PaymentStatus::UNPAID]
            ]);  
   
    }
    //https://billrun.atlassian.net/browse/BRCD-4683
    public function testChargeAccountRejection()
    {
        $this->tester->createAccountWithAllMandatoryCustomFields([
            "payment_gateway" => [
                "active" => [
                    "auth_number" => "9433084",
                    "transaction_exhausted" => true,
                    "card_type" => "00",
                    "card_token" => "1022273188",
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
        $account=$this->tester->getEntity();
        $this->tester->payApi(['aid' => $account['aid'], 'amount' => 400, 'dir' => 'tc']);
        $this->tester->chargeAccountApi(['aids' => (int) $account['aid']]);


        //test the charge bill only for account1
        $this->tester->verifyCollectionRecord(
            'bills',
            [
                'aid' => (int) $account['aid'],
                'amount' => 400,
                'past_rejections'=>['$exists'=>1,'$ne'=>[]]
            ]);

       
   
    }

    //https://billrun.atlassian.net/browse/BRCD-4683
    public function testChargeAccountGetUnknwonResponseFromCg()
    {
        $this->tester->createAccountWithAllMandatoryCustomFields([
            "payment_gateway" => [
                "active" => [
                    "auth_number" => "9433084",
                    "transaction_exhausted" => true,
                    "card_type" => "00",
                    "card_token" => "unknwon",
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
        $account=$this->tester->getEntity();
        $this->tester->payApi(['aid' => $account['aid'], 'amount' => 400, 'dir' => 'tc']);
        $this->tester->chargeAccountApi(['aids' => (int) $account['aid']]);


        //test the charge bill only for account1
        $this->tester->verifyCollectionRecord(
            'bills',
            [
                'aid' => (int) $account['aid'],
                'amount' => 400,
                'paid'=> PaymentStatus::PENDING
                
            ]);

       
   
    }


}