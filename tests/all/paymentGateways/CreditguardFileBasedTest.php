<?php

/**
 * BRCD-5383 — File-based tests for the CreditGuard payment gateway.
 * Covers the full cycle: generate request file → process response file.
 */
class CreditguardFileBasedTest extends \Codeception\Test\Unit
{
    protected $tester;
    private $configModel;

    private const RESPONSE_FIXTURE = 'tests/all/paymentGateways/test_files/cg_response.csv';

    protected function _before()
    {
        $this->configModel = new ConfigModel();
        $this->tester->cleanDB();
        $this->insertCreditGuardFileBasedSettings();
    }

    /**
     * BRCD-5383: The 2-char prefix of the Voucher number in the CG response file
     * must be split into a separate "File number" field. The remaining digits stay
     * in "Voucher number". Both are saved under vendor_response.
     */
    public function testVoucherNumberIsSplitIntoVoucherAndFileNumber()
    {
        $account = $this->createAccountWithCreditGuardPG();
        $this->tester->payApi(['aid' => $account['aid'], 'amount' => 10, 'dir' => 'tc']);
        $this->generateRequestFile();
        $fcTxid = $this->findGeneratedFcTxid($account['aid'], 10);
        $this->processResponseFile($account['aid'], $fcTxid);
        $this->assertVoucherNumberIsSplit($fcTxid);
    }

    // -------------------------------------------------------------------------
    // Setup helpers
    // -------------------------------------------------------------------------

    private function createAccountWithCreditGuardPG(): array
    {
        return $this->tester->createAccountWithAllMandatoryCustomFields([
            'payment_gateway' => [
                'active' => [
                    'name'        => 'CreditGuard',
                    'card_token'      => '1022273188555888',
                    'personal_id'     => '890108566',
                    'card_expiration' => '1228',
                ],
            ],
        ])['entity'];
    }

    private function generateRequestFile(): void
    {
        $generator = Billrun_Generator::getInstance([
            'type'            => 'transactions_request',
            'payment_gateway' => 'CreditGuard',
            'file_type'       => 'transactions1',
        ]);
        $generator->load();
        $generator->generate();
    }

    private function findGeneratedFcTxid(int $aid, float $amount): string
    {
        $fcBill = Billrun_Factory::db()->billsCollection()
            ->query(['dir' => 'fc', 'aid' => $aid, 'amount' => $amount])
            ->cursor()
            ->limit(1)
            ->current();
        $this->assertNotEmpty($fcBill, 'No FC bill found after request file generation');
        return $fcBill['txid'];
    }

    private function processResponseFile(int $aid, string $fcTxid): void
    {
        // Billrun_Processor_Updater::process() calls removefromWorkspace unconditionally,
        // so we work on a /tmp copy, leaving the original test file intact.
        $tmpPath = '/tmp/cg_response_' . $aid . '.csv';
        copy(Billrun_Util::getBillRunPath(self::RESPONSE_FIXTURE), $tmpPath);

        $options = array_merge(
            $this->getCreditGuardConfiguration(),
            [
                'payment_gateway' => 'CreditGuard',
                'file_type'       => 'transactions1',
                'type'            => 'transactions_response',
                'path'            => $tmpPath,
            ]
        );

        // formatLine function reflection — called after each CSV row is parsed, before the
        // txid is used to look up the bill. Replace the file's txid field
        // with the actual FC bill txid lets the static fixture work regardless
        // of what auto-incremented value the generator assigned.
        $processor = new class($options, $fcTxid) extends Billrun_Processor_PaymentGateway_Custom_TransactionsResponse {
            private $fcTxid;
            public function __construct(array $options, string $fcTxid) {
                parent::__construct($options);
                $this->fcTxid = $fcTxid;
            }
            protected function formatLine($row, $dataStructure) {
                $row[$this->tranIdentifierField['field']] = $this->fcTxid;
                return parent::formatLine($row, $dataStructure);
            }
        };

        $processor->processorByPath($options);
    }

    // -------------------------------------------------------------------------
    // Assertions
    // -------------------------------------------------------------------------

    private function getProcessedBill(string $txid): array
    {
        $bill = $this->tester->grabFromCollection('bills', ['txid' => $txid]);
        $this->assertNotEmpty($bill, 'No bill found with txid ' . $txid . ' after response processing');
        return (array) $bill;
    }

    private function assertVoucherNumberIsSplit(string $fcTxid): void
    {
        $bill = $this->getProcessedBill($fcTxid);
        $pgr = (array) $bill['pg_response'];
        $pgrDump = json_encode($pgr);
        $this->assertEquals('28',     $pgr['File number'],    'File number must be the 2-char prefix of the voucher. pg_response: ' . $pgrDump);
        $this->assertEquals('002648', $pgr['Voucher number'], 'Voucher number must be the suffix after stripping the 2-char prefix');
    }

    // -------------------------------------------------------------------------
    // Configuration helpers
    // -------------------------------------------------------------------------

    protected function insertCreditGuardFileBasedSettings()
    {
        $conf = $this->getCreditGuardConfiguration();
        Billrun_Config::getInstance()->loadDbConfig();
        $currentConf = $this->configModel->getConfig();
        $existingIdx = array_search($conf['name'], array_column($currentConf['payment_gateways'] ?? [], 'name'));
        if ($existingIdx !== false) {
            $currentConf['payment_gateways'][$existingIdx] = $conf;
        } else {
            $currentConf['payment_gateways'][] = $conf;
        }
        $pluginConf = [
            'name'         => 'creditGuardPlugin',
            'enabled'      => true,
            'system'       => true,
            'hide_from_ui' => false,
            'configuration' => [
                'values' => [
                    'card_expiration_field_name'       => 'card_expiration',
                    'oldest_card_expiration'           => '20 years ago',
                    'years_to_extend_card_expiration'  => 3,
                    'extend_card_expiration'           => true,
                ],
            ],
        ];
        $existingPluginIdx = array_search('creditGuardPlugin', array_column($currentConf['plugins'] ?? [], 'name'));
        if ($existingPluginIdx !== false) {
            $currentConf['plugins'][$existingPluginIdx] = $pluginConf;
        } else {
            $currentConf['plugins'][] = $pluginConf;
        }
        $this->configModel->setConfig($currentConf);
        Billrun_Config::getInstance()->loadDbConfig();
        $this->attachConfiguredPlugins();
    }

    private function attachConfiguredPlugins(): void
    {
        $dispatcher = Billrun_Factory::dispatcher();
        if (in_array('creditGuard', $dispatcher->getImplementors('beforeUpdatePayments'))) {
            return;
        }
        $values = [
            'card_expiration_field_name'      => 'card_expiration',
            'oldest_card_expiration'          => '20 years ago',
            'years_to_extend_card_expiration' => 3,
            'extend_card_expiration'          => true,
        ];
        $plugin = new creditGuardPlugin($values);
        $dispatcher->attach($plugin);
        $plugin->setAvailability(true);
        $plugin->setOptions($values);
    }

    protected function getCreditGuardConfiguration()
    {
        return [
            "name" => "CreditGuard",
            "transactions_request" => [
                [
                    "file_type" => "transactions1",
                    "export" => [
                        "connection_type"  => "",
                        "host"             => "",
                        "user"             => "",
                        "password"         => "",
                        "remote_directory" => "",
                        "export_directory" => "/tmp",
                    ],
                    "filename" => "billing_test.csv",
                    "filtration" => [
                        "accounts" => [
                            [
                                "field" => "payment_gateway.active.name",
                                "op"    => '$eq',
                                "value" => "CreditGuard",
                            ]
                        ]
                    ],
                    "generator" => [
                        "type"      => "separator",
                        "separator" => ",",
                        "header_structure" => [
                            ["name" => "record_type",            "path" => 1,  "type" => "string", "hard_coded_value" => "000", "mandatory" => true],
                            ["name" => "file_creation_date",     "path" => 2,  "type" => "date",   "format" => "ymdHis", "predefined_values" => "now", "mandatory" => true],
                            ["name" => "file_total_transaction", "path" => 3,  "type" => "string", "predefined_values" => "transactions_num", "mandatory" => true],
                            ["name" => "merchant_file_id",       "path" => 4,  "type" => "string", "hard_coded_value" => ""],
                            ["name" => "filler",                 "path" => 5,  "type" => "string", "hard_coded_value" => ""],
                        ],
                        "data_structure" => [
                            ["name" => "record_type",          "path" => 1,  "type" => "string", "hard_coded_value" => "001", "mandatory" => true],
                            ["name" => "terminal_number",      "path" => 2,  "type" => "string", "hard_coded_value" => "1",   "mandatory" => true],
                            ["name" => "total",                "path" => 3,  "type" => "string", "linked_entity" => ["field_name" => "amount",                             "entity" => "payment_request"], "value_mult" => 100, "mandatory" => true],
                            ["name" => "currency",             "path" => 4,  "type" => "string", "hard_coded_value" => "ILS", "mandatory" => true],
                            ["name" => "card_id",              "path" => 5,  "type" => "string", "linked_entity" => ["field_name" => "payment_gateway.active.card_token", "entity" => "account"],          "mandatory" => true],
                            ["name" => "card_expiration",      "path" => 6,  "type" => "string", "hard_coded_value" => "1",   "mandatory" => true],
                            ["name" => "transaction_type",     "path" => 7,  "type" => "string", "hard_coded_value" => "01",  "mandatory" => true],
                            ["name" => "credit_type",          "path" => 8,  "type" => "string", "hard_coded_value" => "1",   "mandatory" => true],
                            ["name" => "auth_number",          "path" => 9,  "type" => "string", "hard_coded_value" => "01"],
                            ["name" => "user_x_field",         "path" => 10, "type" => "string", "linked_entity" => ["field_name" => "txid",                               "entity" => "payment_request"], "mandatory" => true],
                            ["name" => "id_Number",            "path" => 11, "type" => "string", "linked_entity" => ["field_name" => "payment_gateway.active.personal_id", "entity" => "account"]],
                            ["name" => "add_on_data_z_field",  "path" => 12, "type" => "string", "hard_coded_value" => ""],
                            ["name" => "request_type",         "path" => 13, "type" => "string", "hard_coded_value" => "4",   "mandatory" => true],
                            ["name" => "first_payment",        "path" => 14, "type" => "string", "hard_coded_value" => ""],
                            ["name" => "periodical_payment",   "path" => 15, "type" => "string", "hard_coded_value" => ""],
                            ["name" => "number_of_payments",   "path" => 16, "type" => "string", "hard_coded_value" => ""],
                            ["name" => "tranaction_id_tranId", "path" => 17, "type" => "string", "hard_coded_value" => ""],
                            ["name" => "defer_month",          "path" => 18, "type" => "string", "hard_coded_value" => ""],
                            ["name" => "club_id",              "path" => 19, "type" => "string", "hard_coded_value" => ""],
                            ["name" => "slave_terminal_number","path" => 20, "type" => "string", "hard_coded_value" => ""],
                            ["name" => "ShiftId1",             "path" => 21, "type" => "string", "hard_coded_value" => ""],
                            ["name" => "ShiftId2",             "path" => 22, "type" => "string", "hard_coded_value" => ""],
                            ["name" => "ShiftId3",             "path" => 23, "type" => "string", "hard_coded_value" => ""],
                            ["name" => "userData1",            "path" => 24, "type" => "string", "hard_coded_value" => ""],
                            ["name" => "userData2",            "path" => 25, "type" => "string", "hard_coded_value" => ""],
                            ["name" => "userData3",            "path" => 26, "type" => "string", "hard_coded_value" => ""],
                            ["name" => "userData4",            "path" => 27, "type" => "string", "hard_coded_value" => ""],
                            ["name" => "userData5",            "path" => 28, "type" => "string", "hard_coded_value" => ""],
                            ["name" => "userData6",            "path" => 29, "type" => "string", "hard_coded_value" => ""],
                            ["name" => "userData7",            "path" => 30, "type" => "string", "hard_coded_value" => ""],
                            ["name" => "userData8",            "path" => 31, "type" => "string", "hard_coded_value" => ""],
                            ["name" => "userData9",            "path" => 32, "type" => "string", "hard_coded_value" => ""],
                            ["name" => "userData10",           "path" => 33, "type" => "string", "hard_coded_value" => ""],
                        ],
                        "trailer_structure" => [],
                    ],
                ]
            ],
            "transactions_response" => [
                [
                    "file_type"   => "transactions1",
                    "file_status" => "mixed",
                    "processor"   => [
                        "amount_field"                => "total",
                        "transaction_identifier_field" => "user_x_field",
                        "orphan_files_time"           => "1 hour",
                        "date_field"                  => [
                            "source" => "data",
                            "field"  => "Transmission Date",
                        ],
                        "limit"              => "1",
                        "transaction_status" => [
                            "rejection" => [
                                ["field" => "result", "op" => '$ne', "value" => "000"],
                            ],
                            "success" => [
                                ["field" => "result", "op" => '$eq', "value" => "000"],
                            ],
                        ],
                    ],
                    "parser" => [
                        "type"             => "separator",
                        "separator"        => ",",
                        "header_structure" => [
                            ["name" => "record type",            "checked" => true],
                            ["name" => "merchant file id",       "checked" => true],
                            ["name" => "file total transaction", "checked" => true],
                            ["name" => "file creation date",     "checked" => true],
                            ["name" => "filler",                 "checked" => true],
                        ],
                        "data_structure" => [
                            ["name" => "record type",      "checked" => true, "save_to_bill" => true],
                            ["name" => "user_x_field",     "checked" => true, "save_to_bill" => true],
                            ["name" => "result",           "checked" => true, "save_to_bill" => true],
                            ["name" => "Reference",        "checked" => true, "save_to_bill" => true],
                            ["name" => "Voucher number",   "checked" => true, "save_to_bill" => true],
                            ["name" => "authNumber",       "checked" => true, "save_to_bill" => true],
                            ["name" => "cardId",           "checked" => true, "save_to_bill" => true],
                            ["name" => "Transmission Date","checked" => true, "save_to_bill" => true],
                            ["name" => "total",            "checked" => true, "value_mult" => 0.01, "save_to_bill" => true],
                            ["name" => "cardAcquirer",     "checked" => true, "save_to_bill" => true],
                            ["name" => "tranId",           "checked" => true, "save_to_bill" => true],
                            ["name" => "Defer Month",      "checked" => true, "save_to_bill" => true],
                            ["name" => "cardBrand",        "checked" => true, "save_to_bill" => true],
                            ["name" => "creditCompany",    "checked" => true, "save_to_bill" => true],
                            ["name" => "currency",         "checked" => true, "save_to_bill" => true],
                            ["name" => "transimtion id",   "checked" => true, "save_to_bill" => true],
                            ["name" => "cardType",         "checked" => true, "save_to_bill" => true],
                            ["name" => "terminalNumber",   "checked" => true, "save_to_bill" => true],
                            ["name" => "supplierNumber",   "checked" => true, "save_to_bill" => true],
                            ["name" => "recurringNo",      "checked" => true, "save_to_bill" => true],
                            ["name" => "uid",              "checked" => true, "save_to_bill" => true],
                            ["name" => "cgUid",            "checked" => true, "save_to_bill" => true],
                            ["name" => "userData1",        "checked" => true],
                            ["name" => "userData2",        "checked" => true],
                            ["name" => "userData3",        "checked" => true],
                            ["name" => "userData4",        "checked" => true],
                            ["name" => "userData5",        "checked" => true],
                            ["name" => "userData6",        "checked" => true],
                            ["name" => "userData7",        "checked" => true],
                            ["name" => "userData8",        "checked" => true],
                            ["name" => "userData9",        "checked" => true],
                            ["name" => "userData10",       "checked" => true],
                        ],
                        "trailer_structure" => [],
                        "csv_has_header"    => true,
                        "csv_has_footer"    => false,
                        "line_types"        => ["D" => "//"],
                    ],
                ]
            ],
        ];
    }
}
