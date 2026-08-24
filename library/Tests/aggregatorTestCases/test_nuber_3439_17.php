<?php

class Test_Case_3439_17 {
    public function test_case() {
        return [
    'test' => [
        'test_number' => 173439,
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
            'stamp' => '201807',
            'force_accounts' => [
                353439,
            ],
        ],
    ],
    'expected' => [
        'billrun' => [
            'invoice_id' => 115,
            'billrun_key' => '201807',
            'aid' => 353439,
            'after_vat' => [
                363439 => 117,
                373439 => 117,
                383439 => 117,
                393439 => 117,
            ],
            'total' => 468,
            'vatable' => 400,
            'vat' => 17,
        ],
        'line' => [
            'types' => [
                'flat',
            ],
        ],
    ],
    'duplicate' => true,
];
    }
}
