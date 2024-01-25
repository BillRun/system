<?php

class Test_Case_6 {
    public function test_case() {
        return [
    'test' => [
        'test_number' => 6,
        'aid' => 9,
        'function' => [
            'basicCompare',
            'linesVSbillrun',
            'rounded',
            'passthrough',
        ],
        'options' => [
            'force_accounts' => [
                9,
            ],
        ],
    ],
    'expected' => [
        'billrun' => [
            'invoice_id' => 104,
            'billrun_key' => '201806',
            'aid' => 9,
        ],
    ],
];
    }
}
