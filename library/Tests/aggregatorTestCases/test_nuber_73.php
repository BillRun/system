<?php

class Test_Case_73 {
    public function test_case() {
        return [
    'preRun' => [
        'allowPremature',
        'removeBillruns',
    ],
    'test' => [
        'test_number' => 73,
        'aid' => 1,
        'function' => [
            'MultiDay',
        ],
        'options' => [
            'stamp' => '202311',
            'force_accounts' => [
                10000,
                10027,
                10026,
                10025,
            ],
            'invoicing_days' => [
                '1',
                '28',
            ],
        ],
    ],
    'expected' => [
        'billrun_key' => '202311',
        'accounts' => [
            10000 => '1',
            10027 => '28',
        ],
    ],
    'postRun' => '',
];
    }
}
