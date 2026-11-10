<?php

class realtimeCommonCest
{
    public static $isIPSet = false;

    public function _before(ApiTester $I)
    {
        if (!self::$isIPSet) {
            $I->setSettings('file_types', $this->inputProcessor);
            self::$isIPSet = true;
        }
    }

    public function realtimeRequestEnqueuesLine(ApiTester $I): void
    {
        $request = [
            'sid'    => 1,
            'rate'   => 'rt_common_prod_key_1',
            'date'   => '09/09/2025',
            'volume' => '444',
        ];

        $I->sendRealTimeRequest('rt_common_example_realtime_1', $request);

        $I->verifyCollectionRecord('lines', [
            'uf.sid'   => $request['sid'],
            'uf.rate'  => $request['rate'],
            'in_queue' => true,
        ]);

        $I->verifyCollectionRecord('queue', [
            'uf.sid'  => $request['sid'],
            'uf.rate' => $request['rate'],
        ]);
    }

    public $inputProcessor = [
        "file_type" => "rt_common_example_realtime_1",
        "type" => "realtime",
        "parser" => [
            "type" => "json",
            "separator" => "",
            "structure" => [
                ["name" => "sid", "checked" => true],
                ["name" => "date", "checked" => true],
                ["name" => "rate", "checked" => true],
                ["name" => "volume", "checked" => true],
            ],
            "csv_has_header" => false,
            "csv_has_footer" => false,
            "custom_keys" => ["sid", "date", "rate", "volume"],
            "line_types" => [
                "H" => "/^none$/",
                "D" => "//",
                "T" => "/^none$/",
            ],
        ],
        "processor" => [
            "type" => "Realtime",
            "date_field" => "date",
            "default_usaget" => "call",
            "default_unit" => "seconds",
            "default_volume_src" => ["volume"],
            "orphan_files_time" => "6 hours",
        ],
        "customer_identification_fields" => [
            "call" => [
                [
                    "target_key" => "sid",
                    "src_key" => "sid",
                    "conditions" => [
                        ["field" => "usaget", "regex" => "/.*/"],
                    ],
                    "clear_regex" => "//",
                ],
            ],
        ],
        "rate_calculators" => [
            "retail" => [
                "call" => [
                    "priorities" => [
                        [
                            "filters" => [
                                ["type" => "match", "rate_key" => "key", "line_key" => "rate"],
                            ],
                        ],
                    ],
                ],
            ],
        ],
        "pricing" => [
            "call" => [],
        ],
        "realtime" => [
            "postpay_charge" => true,
        ],
        "response" => [
            "encode" => "json",
            "fields" => [
                ["response_field_name" => "requestNum",    "row_field_name" => "request_num"],
                ["response_field_name" => "requestType",   "row_field_name" => "request_type"],
                ["response_field_name" => "sessionId",     "row_field_name" => "session_id"],
                ["response_field_name" => "returnCode",    "row_field_name" => "granted_return_code"],
                ["response_field_name" => "sid",           "row_field_name" => "sid"],
                ["response_field_name" => "grantedVolume", "row_field_name" => "usagev"],
            ],
        ],
        "unify" => [],
        "enabled" => true,
    ];
}
