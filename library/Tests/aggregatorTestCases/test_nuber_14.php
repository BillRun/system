<?php

class Test_Case_14 {
    public function test_case() {
        return [
    'test' => [
        'test_number' => 14,
        'aid' => 32,
        'sid' => 33,
        'function' => [
            'basicCompare',
            'totalsPrice',
            'lineExists',
            'linesVSbillrun',
            'rounded',
        ],
        'options' => [
            'force_accounts' => [
                32,
            ],
        ],
    ],
    'expected' => [
        'billrun' => [
            'invoice_id' => 112,
            'billrun_key' => '201806',
            'aid' => 32,
            'after_vat' => [
                33 => 117,
            ],
            'total' => 117,
            'vatable' => 100,
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
