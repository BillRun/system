<?php

class Test_Case_3439_16 {
    public function test_case() {
        return [
    'test' => [
        'test_number' => 163439,
        'aid' => 353439,
        'sid' => [
            36,
            37,
            38,
            39,
        ],
        'function' => [
            'basicCompare',
            'sumSids',
            'totalsPrice',
            'lineExists',
            'linesVSbillrun',
            'rounded',
        ],
        'options' => [
            'stamp' => '201806',
            'force_accounts' => [
                353439,
            ],
        ],
    ],
    'expected' => [
        'billrun' => [
            'invoice_id' => 114,
            'billrun_key' => '201806',
            'aid' => 353439,
            'after_vat' => [
                363439 => 128.7,
                373439 => 234,
                383439 => 128.7,
                393439 => 128.7,
            ],
            'total' => 620.1,
            'vatable' => 530,
            'vat' => 17,
        ],
        'line' => [
            'types' => [
                'flat',
                'service',
            ],
        ],
    ],
    'postRun' => [
        'saveId',
    ],
    'duplicate' => true,
];
    }
}
