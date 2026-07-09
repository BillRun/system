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
    protected function sendGenerateRequestFileCommand($account = null, $options = null, $payMode = null) {
        $options = !is_null($options) ? $options : $this->getPgOptions();
        $command = 'php public/index.php --env container --generate --type transactions_request payment_gateway=' . $options['payment_gateway'] . ' file_type=' . $options['file_type'];
        if (!is_null($account)) {
            $command .= " aids=" . $this->tested_whitelist_account['aid'];
        }
        if (!is_null($payMode)) {
            $command .= " pay_mode=" . $payMode;
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
     * BRCD-4676
     *
     * With pay_mode=multiple_payments the generator must group the outstanding
     * bills by their unique_id (invoice_id / txid) rather than by account, so
     * every debt is charged in its OWN payment. This is the opposite of the
     * default one_payment mode, where all of an account's debts are aggregated
     * into a single payment.
     *
     * A single account is given THREE separate debts (10, 20, 30). We also set
     * max_records_per_batch=1 so the loader pulls one bill per batch, which
     * exercises the multiple_payments pagination in getAlreadyLoadedQuery()
     * (the `unique_id => ['$lt' => ...]` cursor). The expected result is THREE
     * distinct fc payments - one covering each debt - and not a single
     * aggregated payment of 60.
     */
    public function testMultiplePaymentsPayModeChargesEachBillSeparately() {
        // one bill per batch, to also cover the multiple_payments pagination cursor
        $this->insertMasavRequestFileSettings(['max_records_per_batch' => 1]);
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

        // Three separate debts for the SAME account.
        $amounts = [10, 20, 30];
        foreach ($amounts as $amount) {
            $this->tester->payApi(['aid' => $account['aid'], 'amount' => $amount, 'dir' => 'tc']);
        }
        $debts = $this->tester->sendBillapiGet(['aid' => $account['aid']], 'bills')['details'];
        $this->assertCount(count($amounts), $debts, 'expected one debt bill per payApi call');

        $this->sendGenerateRequestFileCommand(null, null, 'multiple_payments');

        // Each debt is fully covered by its own dedicated payment.
        foreach ($debts as $debt) {
            $this->tester->verifyCollectionRecord('bills', [
                'aid' => $account['aid'],
                'txid' => $debt['txid'],
                'dir' => $debt['dir'],
                'paid' => "2",
                'pending_covering_amount' => $debt['amount'],
            ]);
            $this->tester->verifyCollectionRecord('bills', [
                'aid' => $account['aid'],
                'dir' => 'fc',
                'generated_pg_file_log' => ['$exists' => true],
                'pending_covering_amount' => $debt['amount'],
                'pays.0.id' => $debt['txid'],
                'pays.0.amount' => ['$eq' => $debt['amount']],
            ]);
        }

        // The defining behaviour of multiple_payments: one payment per debt bill
        // (three fc payments), rather than a single aggregated one.
        $this->tester->verifyCollectionCount('bills', count($amounts), [
            'aid' => $account['aid'],
            'dir' => 'fc',
            'generated_pg_file_log' => ['$exists' => true],
        ]);
    }

    /**
     * BRCD-4676
     *
     * Counterpart to testMultiplePaymentsPayModeChargesEachBillSeparately.
     *
     * With pay_mode=one_payment (the default) the generator groups the
     * outstanding bills by account, so ALL of an account's debts are charged
     * in a SINGLE aggregated payment. A single account is given THREE separate
     * debts (10, 20, 30). We keep max_records_per_batch=1 - here it exercises
     * the account (aid) based pagination cursor in getAlreadyLoadedQuery(). The
     * expected result is exactly ONE fc payment whose 'pays' list covers all
     * three debts, and not three separate payments.
     */
    public function testOnePaymentPayModeAggregatesBillsIntoSinglePayment() {
        // one entity per batch, to also cover the one_payment (aid based) pagination cursor
        $this->insertMasavRequestFileSettings(['max_records_per_batch' => 1]);
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

        // Three separate debts for the SAME account.
        $amounts = [10, 20, 30];
        foreach ($amounts as $amount) {
            $this->tester->payApi(['aid' => $account['aid'], 'amount' => $amount, 'dir' => 'tc']);
        }
        $debts = $this->tester->sendBillapiGet(['aid' => $account['aid']], 'bills')['details'];
        $this->assertCount(count($amounts), $debts, 'expected one debt bill per payApi call');

        $this->sendGenerateRequestFileCommand(null, null, 'one_payment');

        // Every debt is covered, and all of them by the same single payment.
        foreach ($debts as $debt) {
            $this->tester->verifyCollectionRecord('bills', [
                'aid' => $account['aid'],
                'txid' => $debt['txid'],
                'dir' => $debt['dir'],
                'paid' => "2",
                'pending_covering_amount' => $debt['amount'],
            ]);
            $this->tester->verifyCollectionRecord('bills', [
                'aid' => $account['aid'],
                'dir' => 'fc',
                'generated_pg_file_log' => ['$exists' => true],
                'pays' => ['$elemMatch' => ['id' => $debt['txid'], 'amount' => $debt['amount']]],
            ]);
        }

        // The defining behaviour of one_payment: all debts merged into a single
        // fc payment, rather than one payment per bill.
        $this->tester->verifyCollectionCount('bills', 1, [
            'aid' => $account['aid'],
            'dir' => 'fc',
            'generated_pg_file_log' => ['$exists' => true],
        ]);
    }

    /**
     * Function to insert masav data to db
     */
    public function insertMasavRequestFileSettings($generatorOverrides = []) {
        $test_conf = $this->getSampleConfiguration($generatorOverrides);
        $test_pg_name = $test_conf['name'];
        Billrun_Config::getInstance()->loadDbConfig();
        $current_conf = $this->configModel->getConfig();
        // Upsert the masav gateway: the config collection is NOT cleared by
        // cleanDB(), so a masav entry from a previous test persists. A plain
        // "add only if missing" guard would then silently ignore any override
        // (e.g. max_records_per_batch), so replace the existing entry instead.
        $existingIndex = null;
        foreach ($current_conf['payment_gateways'] as $index => $pg) {
            if (isset($pg['name']) && $pg['name'] === $test_pg_name) {
                $existingIndex = $index;
                break;
            }
        }
        if (is_null($existingIndex)) {
            $current_conf['payment_gateways'][] = $test_conf;
        } else {
            $current_conf['payment_gateways'][$existingIndex] = $test_conf;
        }
        $this->configModel->setConfig($current_conf);
        Billrun_Config::getInstance()->loadDbConfig();
        // Drop cached singletons so the just-written config is the one used.
        $this->tester->resetBillrunInstances();
    }

    public function getSampleConfiguration($generatorOverrides = []) {
        $conf = [
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
        if (!empty($generatorOverrides)) {
            $conf['transactions_request'][0]['generator'] = array_merge($conf['transactions_request'][0]['generator'], $generatorOverrides);
        }
        return $conf;
    }
    
}