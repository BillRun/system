<?php

class Test_Case_39 {
    public function test_case() {
        return [
    'test' => [
        'test_number' => 39,
        'aid' => 80,
        'sid' => 81,
        'function' => [
            'basicCompare',
            'subsPrice',
            'lineExists',
            'linesVSbillrun',
            'rounded',
        ],
        'options' => [
            'stamp' => '201904',
            'force_accounts' => [
                80,
            ],
        ],
    ],
    'expected' => [
        'billrun' => [
            'invoice_id' => 135,
            'billrun_key' => '201904',
            'aid' => 80,
            'after_vat' => [
                81 => 101.903225,
            ],
        ],
    ],
    'line' => [
        'types' => [
            'flat',
            'service',
        ],
    ],
];
    }
}
