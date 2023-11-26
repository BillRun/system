<?php

class Test_Case_30 {
    public function test_case() {
        return [
    'test' => [
        'test_number' => 30,
        'aid' => 64,
        'sid' => 65,
        'function' => [
            'basicCompare',
            'totalsPrice',
            'lineExists',
            'linesVSbillrun',
            'rounded',
        ],
        'options' => [
            'stamp' => '201807',
            'force_accounts' => [
                64,
            ],
        ],
    ],
    'expected' => [
        'billrun' => [
            'invoice_id' => 127,
            'billrun_key' => '201807',
            'aid' => 64,
            'after_vat' => [
                65 => 89.7,
            ],
            'total' => 89.7,
            'vatable' => 76.666666667,
            'vat' => 17,
        ],
        'line' => [
            'types' => [
                'flat',
            ],
        ],
    ],
];
    }
}
