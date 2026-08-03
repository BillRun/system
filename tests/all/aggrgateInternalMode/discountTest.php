<?php

class discountTest extends \Codeception\Test\Unit
{
    /**
     * @var \UnitTester
     */
    protected $tester;

    protected $epsilon = 0.00001;

    public $defaultOptions = array(
        "type" => "customer",
        "page" => 0,
        "size" => 100,
        'fetchonly' => true,
        'generate_pdf' => 0,
    );

    protected function _before()
    {
        ini_set('error_reporting', E_ALL & ~E_WARNING & ~E_NOTICE);
        $this->tester->enableDBModeSettings();      
    }

    protected function _after()
    {
    }

        public function testDiscountsWith2conditions()
    {
        /*
        BRCD-5086  
        Discount with a condition about the plan and a condition about the service.  
        The plan condition is met for 100% of the month, and the service is met from mid-month.
        */
        $this->tester->generatePlan();
        $plan = json_decode($this->tester->grabResponse(), true)['entity'];
        $this->tester->createAccountWithAllMandatoryCustomFields();
        $account = json_decode($this->tester->grabResponse(), true)['entity'];
        $this->defaultOptions['stamp'] = '202511';
        $this->defaultOptions['force_accounts'] = [$account['aid']];
        $service = $this->tester->generateService(['name'=>'SERVICE_DISCOUNT_TEST'.microtime(false)*1000000]);
        $subscriber = $this->tester->generateSubscriber(
            [
                 'from' => '2025-09-10T10:00:00Z',
                 'to' => '2124-01-01T00:00:00Z',
                'aid' => $account['aid'],
                'plan' => $plan['name'],
                'services' => [
                    [
                        'name' => $service['name'],
                        'from' => '2025-10-10T10:00:00Z',
                        'to' => '2124-01-01T00:00:00Z'
                    ],

                ]
            ]
        );
        $subscriber = json_decode($this->tester->grabResponse(), true)['entity'];
        $discountName = "DISCOUNT" . microtime(false)*1000000;
        $this->tester->generateDiscount([
            "from" => "2024-08-01T21:00:00Z",
            "to" => "2125-11-06T05:00:00Z",
            "params" => [
                "conditions" => [
                    [
                        "subscriber" => [
                            [
                                "fields" => [
                                    [
                                        "field" => "plan",
                                        "op" => "in",
                                        "value" => [
                                            $plan['name']
                                        ]
                                    ]
                                ],
                                "service" => [
                                    "any" => [
                                        [
                                            "fields" => [
                                                [
                                                    "field" => "name",
                                                    "op" => "in",
                                                    "value" => [
                                                       $service['name']
                                                    ]
                                                ]
                                            ]
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ],

            "subject" => [
                "plan" => [
                    $plan['name'] => ["value" => 20]
                ]
            ],
            'key' => $discountName

        ]);

        $this->tester->runCycle($this->defaultOptions);
        $discountLine1 = $this->tester->grabFromCollection('lines', array('type' => "credit", "usaget" => "discount", 'aid' => $account['aid'], 'key' => $discountName));
        $this->assertEqualsWithDelta(-14.193548387096774, $discountLine1['aprice'], $this->epsilon);
    }

}