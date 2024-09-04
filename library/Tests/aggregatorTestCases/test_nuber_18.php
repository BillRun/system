<?php

class Test_Case_18 {
    public function test_case() {
        return [
    'test' => [
        'test_number' => 18,
        'aid' => 40,
        'sid' => 41,
        'function' => [
            'basicCompare',
            'totalsPrice',
            'lineExists',
            'linesVSbillrun',
            'rounded',
        ],
        'options' => [
            'stamp' => '201806',
            'force_accounts' => [
                40,
            ],
        ],
    ],
    'expected' => [
        'billrun' => [
            'invoice_id' => 116,
            'billrun_key' => '201806',
            'aid' => 40,
            'after_vat' => [
                41 => 351,
            ],
            'total' => 351,
            'vatable' => 300,
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
