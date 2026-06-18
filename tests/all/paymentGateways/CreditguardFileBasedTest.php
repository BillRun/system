<?php

use Codeception\Lib\ModuleContainer;
use Codeception\Module\Cli;

/**
 * BRCD-5384
 *
 * First file-based tests for the CreditGuard payment gateway.
 * Covers the full cycle: generate request file → process response file.
 */
class CreditguardFileBasedTest extends \Codeception\Test\Unit
{
    /**
     * @var \AcceptanceTester
     */
    protected $tester;
    private $cli;
    private $configModel;

    protected function _before()
    {
        $moduleContainer = new ModuleContainer(new \Codeception\Lib\Di(), []);
        $this->cli = new Cli($moduleContainer);
        $this->configModel = new ConfigModel();
        $this->tester->cleanDB();
        $this->insertCreditGuardFileBasedSettings();
    }

    /**
     * BRCD-5384
     *
     * Full file-based charge cycle for CreditGuard:
     *  1. Create an account with CreditGuard PG and a debt (TC bill).
     *  2. Generate the request file → pending FC payment bill.
     *  3. Supply a response CSV file with result=000 (success) and multiple save_to_bill fields.
     *  4. Process the response file.
     *  5. Assert:
     *     - All save_to_bill fields are stored under vendor_response.
     *     - vendor_response.name and vendor_response.status (set by setPaymentStatus
     *       before the merge) are NOT overwritten.
     *     - The old pg_response field does not exist.
     *     - The payment is confirmed (pending = false).
     */
    public function testVendorResponseMergesFromResponseFile()
    {
        $account = $this->tester->createAccountWithAllMandatoryCustomFields([
            "payment_gateway" => [
                "active" => [
                    "name"       => "CreditGuard",
                    "card_token" => "1022273188555888",
                    "personal_id" => "012345678",
                ]
            ]
        ])['entity'];

        $this->tester->payApi(['aid' => $account['aid'], 'amount' => 10, 'dir' => 'tc']);

        // Generate the request file — creates a pending FC payment bill.
        $this->cli->runShellCommand(
            'php public/index.php --env container --generate --type transactions_request'
            . ' payment_gateway=CreditGuard file_type=transactions1'
        );

        // Retrieve the generated FC bill so we can update its txid to the fixed
        // value used in the static response file.
        $fcBill = $this->tester->grabFromCollection('bills', [
            'aid'                   => $account['aid'],
            'dir'                   => 'fc',
            'generated_pg_file_log' => ['$exists' => true],
        ]);
        $this->assertNotEmpty($fcBill, 'Expected a pending FC bill after request file generation');

        // Pin the txid to the value hardcoded in the static response CSV so the
        // processor can look up the bill by its transaction identifier.
        $knownTxid = '0000000000004';
        $billsCollection = Billrun_Factory::db()->billsCollection();
        $billsCollection->update(
            ['txid' => $fcBill['txid']],
            ['$set' => ['txid' => $knownTxid]]
        );
        $billsCollection->update(
            ['aid' => $account['aid'], 'dir' => 'tc', 'paid_by.id' => $fcBill['txid']],
            ['$set' => ['paid_by.$.id' => $knownTxid]]
        );

        $this->tester->processByPath([
            'type'            => 'transactions_response',
            'payment_gateway' => 'CreditGuard',
            'file_type'       => 'transactions1',
            'path'            => 'tests/all/paymentGateways/test_files/cg_response.csv',
        ]);

        // save_to_bill fields must be stored under vendor_response.
        // total: 1100 in the file * value_mult 0.01 = 11.
        $this->tester->verifyCollectionRecord('bills', [
            'txid'                      => $knownTxid,
            'vendor_response.result'    => '000',
            'vendor_response.Reference' => '10427223',
            'vendor_response.total'     => 11,
            'vendor_response.uid'       => '291020241500088280000001',
            'vendor_response.cgUid'     => '0000000000004',
        ]);

        // vendor_response.name and vendor_response.status set by setPaymentStatus
        // must survive the subsequent setExtraFields merge call.
        $this->tester->verifyCollectionRecord('bills', [
            'txid'                   => $knownTxid,
            'vendor_response.name'   => 'CreditGuard',
            'vendor_response.status' => 'mixed',
        ]);

        // The old pg_response field must not exist.
        $this->tester->verifyCollectionRecord('bills', [
            'txid'        => $knownTxid,
            'pg_response' => ['$exists' => false],
        ]);

        // The payment must be confirmed (no longer pending).
        $this->tester->verifyCollectionRecord('bills', [
            'txid'    => $knownTxid,
            'pending' => false,
        ]);
    }

    // -------------------------------------------------------------------------
    // Configuration helpers
    // -------------------------------------------------------------------------

    protected function insertCreditGuardFileBasedSettings()
    {
        $conf = $this->getCreditGuardConfiguration();
        Billrun_Config::getInstance()->loadDbConfig();
        $currentConf = $this->configModel->getConfig();
        if (!in_array($conf['name'], array_column($currentConf['payment_gateways'], 'name'))) {
            $currentConf['payment_gateways'][] = $conf;
            $this->configModel->setConfig($currentConf);
        }
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
                            ["name" => "record_type",         "path" => 1,  "type" => "string", "hard_coded_value" => "001", "mandatory" => true],
                            ["name" => "terminal_number",     "path" => 2,  "type" => "string", "hard_coded_value" => "1",   "mandatory" => true],
                            ["name" => "total",               "path" => 3,  "type" => "string", "linked_entity" => ["field_name" => "amount",                             "entity" => "payment_request"], "value_mult" => 100, "mandatory" => true],
                            ["name" => "currency",            "path" => 4,  "type" => "string", "hard_coded_value" => "ILS", "mandatory" => true],
                            ["name" => "card_id",             "path" => 5,  "type" => "string", "linked_entity" => ["field_name" => "payment_gateway.active.card_token", "entity" => "account"],          "mandatory" => true],
                            ["name" => "card_expiration",     "path" => 6,  "type" => "string", "hard_coded_value" => "1",   "mandatory" => true],
                            ["name" => "transaction_type",    "path" => 7,  "type" => "string", "hard_coded_value" => "01",  "mandatory" => true],
                            ["name" => "credit_type",         "path" => 8,  "type" => "string", "hard_coded_value" => "1",   "mandatory" => true],
                            ["name" => "auth_number",         "path" => 9,  "type" => "string", "hard_coded_value" => "01"],
                            ["name" => "user_x_field",        "path" => 10, "type" => "string", "linked_entity" => ["field_name" => "txid",                               "entity" => "payment_request"], "mandatory" => true],
                            ["name" => "id_Number",           "path" => 11, "type" => "string", "linked_entity" => ["field_name" => "payment_gateway.active.personal_id", "entity" => "account"]],
                            ["name" => "add_on_data_z_field", "path" => 12, "type" => "string", "hard_coded_value" => ""],
                            ["name" => "request_type",        "path" => 13, "type" => "string", "hard_coded_value" => "4",   "mandatory" => true],
                            ["name" => "first_payment",       "path" => 14, "type" => "string", "hard_coded_value" => ""],
                            ["name" => "periodical_payment",  "path" => 15, "type" => "string", "hard_coded_value" => ""],
                            ["name" => "number_of_payments",  "path" => 16, "type" => "string", "hard_coded_value" => ""],
                            ["name" => "tranaction_id_tranId","path" => 17, "type" => "string", "hard_coded_value" => ""],
                            ["name" => "defer_month",         "path" => 18, "type" => "string", "hard_coded_value" => ""],
                            ["name" => "club_id",             "path" => 19, "type" => "string", "hard_coded_value" => ""],
                            ["name" => "slave_terminal_number","path" => 20,"type" => "string", "hard_coded_value" => ""],
                            ["name" => "ShiftId1",            "path" => 21, "type" => "string", "hard_coded_value" => ""],
                            ["name" => "ShiftId2",            "path" => 22, "type" => "string", "hard_coded_value" => ""],
                            ["name" => "ShiftId3",            "path" => 23, "type" => "string", "hard_coded_value" => ""],
                            ["name" => "userData1",           "path" => 24, "type" => "string", "hard_coded_value" => ""],
                            ["name" => "userData2",           "path" => 25, "type" => "string", "hard_coded_value" => ""],
                            ["name" => "userData3",           "path" => 26, "type" => "string", "hard_coded_value" => ""],
                            ["name" => "userData4",           "path" => 27, "type" => "string", "hard_coded_value" => ""],
                            ["name" => "userData5",           "path" => 28, "type" => "string", "hard_coded_value" => ""],
                            ["name" => "userData6",           "path" => 29, "type" => "string", "hard_coded_value" => ""],
                            ["name" => "userData7",           "path" => 30, "type" => "string", "hard_coded_value" => ""],
                            ["name" => "userData8",           "path" => 31, "type" => "string", "hard_coded_value" => ""],
                            ["name" => "userData9",           "path" => 32, "type" => "string", "hard_coded_value" => ""],
                            ["name" => "userData10",          "path" => 33, "type" => "string", "hard_coded_value" => ""],
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
