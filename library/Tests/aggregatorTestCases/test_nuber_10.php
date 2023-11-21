<?php

class Test_Case_10 {
    public function test_case() {
        return [
    'test' => [
        'test_number' => 10,
        'aid' => 21,
        'sid' => 20,
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
                21,
            ],
        ],
    ],
    'expected' => [
        'billrun' => [
            'invoice_id' => 108,
            'billrun_key' => '201806',
            'aid' => 21,
            'after_vat' => [
                21 => 57.745161289,
            ],
            'total' => 57.745161289,
            'vatable' => 49.354838709677416,
            'vat' => 17,
        ],
        'line' => [
            'types' => [
                'flat',
                'credit',
            ],
        ],
    ],
];
    }
}
