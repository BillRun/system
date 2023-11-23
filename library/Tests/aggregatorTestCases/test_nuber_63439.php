<?php

class Test_Case_63439 {
    public function test_case() {
        return [
    'test' => [
        'test_number' => 63439,
        'aid' => 93439,
        'function' => [
            'basicCompare',
            'linesVSbillrun',
            'rounded',
            'passthrough',
        ],
        'options' => [
            'force_accounts' => [
                93439,
            ],
        ],
    ],
    'expected' => [
        'billrun' => [
            'invoice_id' => 104,
            'billrun_key' => '201806',
            'aid' => 93439,
        ],
    ],
    'duplicate' => true,
];
    }
}
