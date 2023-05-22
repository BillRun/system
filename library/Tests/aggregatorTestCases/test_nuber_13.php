<?php

class Test_Case_13 {
    public function test_case() {
        return [
    'test' => [
        'test_number' => 13,
        'aid' => 30,
        'sid' => 31,
        'function' => [
            'basicCompare',
            'totalsPrice',
            'lineExists',
            'linesVSbillrun',
            'rounded',
            'passthrough',
        ],
        'options' => [
            'stamp' => '201806',
            'force_accounts' => [
                30,
            ],
        ],
    ],
    'expected' => [
        'billrun' => [
            'invoice_id' => 111,
            'billrun_key' => '201806',
            'aid' => 30,
            'after_vat' => [
                31 => 234,
            ],
            'total' => 234,
            'vatable' => 200,
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
