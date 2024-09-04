<?php

class Test_Case_3439_39 {
    public function test_case() {
        return [
    'test' => [
        'test_number' => 393439,
        'aid' => 803439,
        'sid' => 813439,
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
                803439,
            ],
        ],
    ],
    'expected' => [
        'billrun' => [
            'invoice_id' => 135,
            'billrun_key' => '201904',
            'aid' => 803439,
            'after_vat' => [
                813439 => 101.903225,
            ],
        ],
    ],
    'line' => [
        'types' => [
            'flat',
            'service',
        ],
    ],
    'duplicate' => true,
];
    }
}
