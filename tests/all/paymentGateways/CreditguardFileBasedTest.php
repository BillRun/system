<?php

/**
 * BRCD-5384 — First file-based tests for the CreditGuard payment gateway.
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
     * BRCD-5384: pg_response fields from the response file must land under
     * vendor_response, and the original vendor_response fields must "survive"
     * the merge. The old pg_response field must not exist.
     */
    public function testVendorResponseMergesFromResponseFile()
    {
        $account = $this->createAccountWithCreditGuardPG();
        $this->tester->payApi(['aid' => $account['aid'], 'amount' => 10, 'dir' => 'tc']);
        $this->generateRequestFile();
        $fcTxid = $this->findGeneratedFcTxid($account['aid'], 10);
        $this->processResponseFile($account['aid'], $fcTxid);
        $this->assertPgResponseUnderVendorResponse($fcTxid);
        $this->assertVendorResponseIdentityPreserved($fcTxid);
        $this->assertNoPgResponseField($fcTxid);
        $this->assertPaymentConfirmed($fcTxid);
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
                    'card_token'  => '1022273188555888',
                    'personal_id' => '890108566',
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

        // Override formatLine — called after each CSV row is parsed, before the
        // txid is used to look up the bill. Substituting the file's txid field
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

    private function assertPgResponseUnderVendorResponse(string $fcTxid): void
    {
        $bill = $this->getProcessedBill($fcTxid);
        $vr = (array) $bill['vendor_response'];
        // user_x_field is the txid overridden by formatLine; its saved
        // value proves the substitution ran and the right bill was matched.
        $this->assertEquals($fcTxid,                   $vr['user_x_field'], 'vendor_response.user_x_field equals runtime txid');
        // total: 1100 in the file × value_mult 0.01 = 11
        $this->assertEquals('000',                     $vr['result'],    'vendor_response.result');
        $this->assertEquals('10427223',                $vr['Reference'], 'vendor_response.Reference');
        $this->assertEquals(11,                        $vr['total'],     'vendor_response.total');
        $this->assertEquals('291020241500088280000001', $vr['uid'],       'vendor_response.uid');
        $this->assertEquals('8867300000004',           $vr['cgUid'],     'vendor_response.cgUid');
    }

    private function assertVendorResponseIdentityPreserved(string $fcTxid): void
    {
        $bill = $this->getProcessedBill($fcTxid);
        $vr = (array) $bill['vendor_response'];
        $this->assertEquals('CreditGuard', $vr['name'],   'vendor_response.name');
        $this->assertEquals('mixed',       $vr['status'], 'vendor_response.status');
    }

    private function assertNoPgResponseField(string $fcTxid): void
    {
        $bill = $this->getProcessedBill($fcTxid);
        $this->assertArrayNotHasKey('pg_response', $bill, 'pg_response field must not exist after BRCD-5384');
    }

    private function assertPaymentConfirmed(string $fcTxid): void
    {
        $bill = $this->getProcessedBill($fcTxid);
        $this->assertFalse((bool) $bill['pending'], 'Payment must be confirmed (pending must be false)');
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
        $this->configModel->setConfig($currentConf);
        Billrun_Config::getInstance()->loadDbConfig();
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
