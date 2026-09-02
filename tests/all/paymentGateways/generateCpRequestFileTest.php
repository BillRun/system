<?php

use Codeception\Lib\ModuleContainer;
use Codeception\Module\Cli;

class generateCpRequestFileTest extends \Codeception\Test\Unit
{
    /**
     * @var \UnitTester
     */
    protected $tester;
    private $cli;
    protected $configModel;
    protected $general_locked_account;
    protected $tested_account;
    protected $tested_whitelist_account;
    protected $tested_bill;
    protected $tested_whitelist_bill;

    protected function _before() {
        $moduleContainer = new ModuleContainer(new \Codeception\Lib\Di(), []);
        $this->cli = new Cli($moduleContainer);
        $this->configModel = new ConfigModel();
        $this->tester->cleanDB();
    }

    protected function _after()
    {
    }
    
    /**
     * Function to check if the lock operation locks the right account, and creates request file
     */
    public function testLockOperationProcess() {
        $this->setRelevantData();
        $this->insertMasavRequestFileSettings();
        $this->sendGenerateRequestFileCommand();
        $this->checkTestAccountsBillWasPaidByCpGenerateCommand($this->tested_bill);
        $this->sendGenerateRequestFileWithWhitelistCommand();
        $this->checkTestAccountsBillWasPaidByCpGenerateCommand($this->tested_whitelist_bill);
    }

    /**
     * Function to set relevant test data
     */
    protected function setRelevantData() {
        //create general account just to have operation lock to test the lock of the tested account
        $this->general_locked_account = $this->tester->createAccountWithAllMandatoryCustomFields()['entity'];
        //need to create general operation object
        $this->tester->addOperationToDb("charge_account", $this->general_locked_account['aid'], new \DateTime(), new \DateTime('+1 hour'));
        //create account with masav gateway
        $this->tested_account = $this->tester->createAccountWithAllMandatoryCustomFields([
            "payment_gateway" => [
                "active" => [
                    "name" => "masav",
                    "bank_code" => 1,
                    "bank_branch_num" => 1,
                    "account_num" => 1,
                    "customer_id" => 1
                ]
            ]
        ])['entity'];
        //created debt that will be pulled to the cpg request file
        $this->tester->payApi(['aid' => $this->tested_account['aid'], 'amount' => 10, 'dir' => 'tc']);
        $this->tested_bill = current($this->tester->sendBillapiGet(['aid' => $this->tested_account['aid']],'bills')['details']);
    }

    /**
     * Sends generates request file command
     *
     * @param array $account in case whitelist is sent to the command
     * @return Cli cli object
     */
    protected function sendGenerateRequestFileCommand($account = null, $options = null) {
        $options = !is_null($options) ? $options : $this->getPgOptions();
        $command = 'php public/index.php --env container --generate --type transactions_request payment_gateway=' . $options['payment_gateway'] . ' file_type=' . $options['file_type'];
        if (!is_null($account)) {
            $command .= " aids=" . $this->tested_whitelist_account['aid'];
        }
        $this->cli->runShellCommand($command);
        return $this->cli;
    }

    /**
     * Function to set new data for "whitelist" check + execute command
     */
    protected function sendGenerateRequestFileWithWhitelistCommand() {
        //create account with masav gateway
        $this->tested_whitelist_account = $this->tester->createAccountWithAllMandatoryCustomFields([
            "payment_gateway" => [
                "active" => [
                    "name" => "masav",
                    "bank_code" => 1,
                    "bank_branch_num" => 1,
                    "account_num" => 1,
                    "customer_id" => 1
                ]
            ]
        ])['entity'];
        //created debt that will be pulled to the cpg request file
        $this->tester->payApi(['aid' => $this->tested_whitelist_account['aid'], 'amount' => 10, 'dir' => 'tc']);
        $this->tested_whitelist_bill = current($this->tester->sendBillapiGet(['aid' => $this->tested_whitelist_account['aid']],'bills')['details']);
        $this->sendGenerateRequestFileCommand($this->tested_whitelist_bill);
    }

    public function getPgOptions() {
        return ['payment_gateway' => 'masav', 'file_type' => 'masav_request', 'parameters' => []];
    }

    /**
     * Function to check the result. As decided, checking the effected bills is enough.
     */
    public function checkTestAccountsBillWasPaidByCpGenerateCommand($bill) {
        $this->tester->verifyCollectionRecord(
            'bills',
            [
                'aid' => $bill['aid'],
                'txid' => $bill['txid'],
                'dir' => $bill['dir'],
                'paid' => "2",
                'pending_covering_amount' => $bill['amount']
            ]);
        $this->tester->verifyCollectionRecord(
            'bills',
            [
                'aid' => $bill['aid'],
                'dir' => 'fc',
                'generated_pg_file_log' => ['$exists' => true],
                'pending_covering_amount' => $bill['amount'],
                'pays.id' => $bill['txid'],
                'pays.amount' => ['$eq' => $bill['amount']]
            ]);
    }

    /**
     * BRCD-5302
     */
    public function testNoPerRowAccountLoadAfterBulkLoad() {
        $this->insertMasavRequestFileSettings();
        $accounts = [];
        for ($i = 0; $i < 2; $i++) {
            $customerId = 100 + $i;
            $account = $this->tester->createAccountWithAllMandatoryCustomFields([
                "payment_gateway" => [
                    "active" => [
                        "name" => "masav",
                        "bank_code" => 1,
                        "bank_branch_num" => 1,
                        "account_num" => 1,
                        "customer_id" => $customerId
                    ]
                ]
            ])['entity'];
            $this->tester->payApi(['aid' => $account['aid'], 'amount' => 10, 'dir' => 'tc']);
            $accounts[] = ['aid' => $account['aid'], 'customer_id' => $customerId];
        }
        $this->tester->clearLogFile();
        $this->sendGenerateRequestFileCommand();
        $this->tester->dontSeeInLogFile('Custom PG generator: preloaded account missing');
        foreach ($accounts as $acc) {
            $expectedSavedCustomerId = str_pad((string) $acc['customer_id'], 9, '0', STR_PAD_LEFT);
            $this->tester->verifyCollectionRecord('bills', [
                'aid' => $acc['aid'],
                'dir' => 'fc',
                'generated_pg_file_log' => ['$exists' => true],
                'pg_request.customer_id' => $expectedSavedCustomerId,
            ]);
        }
    }

    /**
     * BRCD-5327
     *
     * The `value_mult` configuration in the generator's data_structure must be
     * applied against the amount exactly ONCE, not twice.
     *
     * With amount = 5 and value_mult = 100 the saved value must be 500
     * (5 * 100), and NOT 50000 (5 * 100 * 100).
     *
     * The amount is saved to the bill (save_to_bill => true) under
     * `pg_request.value_mult_amount`, so we assert the single-multiplication
     * result there.
     */
    public function testValueMultAppliedOnceInDataStructure() {
        $this->insertMasavRequestFileSettings();
        $account = $this->tester->createAccountWithAllMandatoryCustomFields([
            "payment_gateway" => [
                "active" => [
                    "name" => "masav",
                    "bank_code" => 1,
                    "bank_branch_num" => 1,
                    "account_num" => 1,
                    "customer_id" => 1
                ]
            ]
        ])['entity'];
        //created debt of 5 that will be pulled to the cpg request file
        $this->tester->payApi(['aid' => $account['aid'], 'amount' => 5, 'dir' => 'tc']);

        $this->sendGenerateRequestFileCommand();

        $this->tester->verifyCollectionRecord('bills', [
            'aid' => $account['aid'],
            'dir' => 'fc',
            'generated_pg_file_log' => ['$exists' => true],
            'pg_request.value_mult_amount' => 500,
        ]);
    }

    /**
     * BRCD-5327
     *
     * Regression guard: every formatting attribute handled by
     * Billrun_Util::formattingValue() (value_mult, number_format, date,
     * substring, padding) must be applied to the data_structure value exactly
     * ONCE. A second application (the original value_mult bug) would corrupt the
     * value saved to the bill, so we assert the single-application result of each
     * attribute on its matching pg_request.<field>.
     */
    public function testFormattingAttributesAppliedOnceInDataStructure() {
        $this->insertMasavRequestFileSettings();
        
        $account = $this->tester->createAccountWithAllMandatoryCustomFields([
            "payment_gateway" => [
                "active" => [
                    "name" => "masav",
                    "bank_code" => 1,
                    "bank_branch_num" => 1,
                    "account_num" => 1,
                    "customer_id" => 1
                ]
            ]
        ])['entity'];
        //created debt of 5 that will be pulled to the cpg request file
        $this->tester->payApi(['aid' => $account['aid'], 'amount' => 5, 'dir' => 'tc']);

        $this->sendGenerateRequestFileCommand();

        $this->tester->verifyCollectionRecord('bills', [
            'aid' => $account['aid'],
            'dir' => 'fc',
            'generated_pg_file_log' => ['$exists' => true],
            'pg_request.value_mult_amount' => 500,         // value_mult applied once (doubled => 50000)
            'pg_request.customer_id'       => '000000001', // padding applied once (customer_id=1, len 9)
            'pg_request.reg_number_format' => '1,234.50',  // number_format applied once (doubled => "1.00")
            'pg_request.reg_date'          => '20200115',  // date applied once (doubled => "19700822")
            'pg_request.reg_substring'     => 'BCD',       // substring applied once (doubled => "CD")
            'pg_request.reg_padding'       => '******42',  // padding applied once
            'pg_request.reg_relative_time' => '20200114',  // date - 1 day, applied once (doubled => "20200113")
        ]);
    }

    /**
     * Function to insert masav data to db
     */
    public function insertMasavRequestFileSettings() {
        $test_conf = $this->getSampleConfiguration();
        $test_pg_name = $test_conf['name'];
        Billrun_Config::getInstance()->loadDbConfig();
        $current_conf = $this->configModel->getConfig();
        if (!in_array($test_pg_name, array_column($current_conf['payment_gateways'], "name"))) {
            $current_conf['payment_gateways'][] = $test_conf;
            $this->configModel->setConfig($current_conf);
        }
        Billrun_Config::getInstance()->loadDbConfig();
    }

    public function getSampleConfiguration() {
        return [
			"name" => "masav",
			"custom" => true,
			"transactions_request" => [
				[
					"file_type" => "masav_request",
					"export" => [
						"connection_type" => "",
						"host" => "",
						"user" => "",
						"password" => "",
						"remote_directory" => "",
						"export_directory" => "/tmp"
                    ],
					"filename" => "msv[[param2_seq]].csv",
					"filename_params" => [
						[
							"param" => "param2_seq",
							"type" => "autoinc",
							"min_value" => 0,
							"max_value" => 999,
							"date_group" => "",
							"padding" => [
								"character" => "0",
								"length" => 3,
								"direction" => "left"
                            ],
							"value" => "now"
                        ]
					],
					"filtration" => [
						"placeholders" => [
							[
								"field" => "payment_direction",
								"op" => '$eq',
								"value" => "fc"
                            ]
						],
						"accounts" => [
							[
								"field" => "payment_gateway.active.name",
								"op" => '$eq',
								"value" => "masav"
                            ]
						]
                    ],
					"generator" => [
						"type" => "fixed",
						"separator" => "",
						"encoding" => "CP862",
						"header_structure" => [
							[
								"name" => "record_id",
								"path" => 1,
								"type" => "string",
								"hard_coded_value" => "K"
                            ]
						],
						"data_structure" => [
							[
								"name" => "value_mult_amount",
								"path" => 4,
								"type" => "number",
								"value_mult" => 100,
								"save_to_bill" => true,
								"linked_entity" => [
									"field_name" => "amount",
									"entity" => "payment_request"
								]
							],
							[
								"name" => "customer_id",
								"path" => 1,
								"type" => "string",
								"save_to_bill" => true,
								"padding" => [
									"character" => "0",
									"length" => 9,
									"direction" => "left"
                                ],
								"linked_entity" => [
									"field_name" => "payment_gateway.active.customer_id",
									"entity" => "account"
                                ]
							],
							[
								"name" => "transaction_amount",
								"path" => 2,
								"padding" => [
									"character" => "0",
									"length" => 13,
									"direction" => "left"
                                ],
								"number_format" => [
									"decimals" => 2,
									"dec_point" => "",
									"thousands_sep" => ""
                                ],
								"type" => "string",
								"linked_entity" => [
									"field_name" => "amount",
									"entity" => "payment_request"
                                ]
                            ],
							[
								"name" => "transaction_id",
								"path" => 3,
								"padding" => [
									"character" => "0",
									"length" => 20,
									"direction" => "left"
                                ],
								"type" => "string",
								"linked_entity" => [
									"field_name" => "txid",
									"entity" => "payment_request"
                                ]
                            ],
							[
								"name" => "reg_number_format",
								"path" => 5,
								"type" => "number",
								"hard_coded_value" => 1234.5,
								"number_format" => [
									"decimals" => 2,
									"dec_point" => ".",
									"thousands_sep" => ","
								],
								"save_to_bill" => true
							],
							[
								"name" => "reg_date",
								"path" => 6,
								"type" => "date",
								"format" => "Ymd",
								"hard_coded_value" => 1579089600, // 2020-01-15 12:00:00 UTC
								"save_to_bill" => true
							],
							[
								"name" => "reg_substring",
								"path" => 7,
								"type" => "string",
								"hard_coded_value" => "ABCDEF",
								"substring" => [
									"offset" => 1,
									"length" => 3
								],
								"save_to_bill" => true
							],
							[
								"name" => "reg_padding",
								"path" => 8,
								"type" => "string",
								"hard_coded_value" => "42",
								"padding" => [
									"character" => "*",
									"length" => 8,
									"direction" => "left"
								],
								"save_to_bill" => true
							],
							[
								"name" => "reg_relative_time",
								"path" => 9,
								"type" => "date",
								"format" => "Ymd",
								"relative_time" => "-1 day",
								"hard_coded_value" => 1579089600, // 2020-01-15 12:00:00 UTC
								"save_to_bill" => true
							]
						],
						"trailer_structure" => [
							[
								"name" => "record_typ",
								"path" => 1,
								"hard_coded_value" => "5"
                            ],
							[
								"name" => "number_of_transactions",
								"path" => "10",
								"type" => "string",
								"padding" => [
									"character" => "0",
									"length" => 7,
									"direction" => "left"
                                ],
								"predefined_values" => "transactions_num"
                            ]
						]
                    ]
                ]
			]
        ];
    }

    
    
}