<?php

class Test_Case_16 {
    public function test_case() {
        return [
    'test' => [
        'test_number' => 16,
        'aid' => 35,
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
                35,
            ],
        ],
    ],
    'expected' => [
        'billrun' => [
            'invoice_id' => 114,
            'billrun_key' => '201806',
            'aid' => 35,
            'after_vat' => [
                36 => 128.7,
                37 => 234,
                38 => 128.7,
                39 => 128.7,
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
];
    }
}
