<?php

class Test_Case_11 {
    public function test_case() {
        return [
    'test' => [
        'test_number' => 11,
        'aid' => 25,
        'sid' => 26,
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
                25,
            ],
        ],
    ],
    'expected' => [
        'billrun' => [
            'invoice_id' => 109,
            'billrun_key' => '201806',
            'aid' => 25,
            'after_vat' => [
                26 => 52.83870967741935,
            ],
            'total' => 52.83870967741935,
            'vatable' => 45.16129032258064,
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
