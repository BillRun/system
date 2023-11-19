<?php

class Test_Case_4 {
    public function test_case() {
        return [
    'test' => [
        'test_number' => 4,
        'aid' => 5,
        'function' => [
            'basicCompare',
            'linesVSbillrun',
            'rounded',
            'passthrough',
        ],
        'options' => [
            'force_accounts' => [
                5,
            ],
        ],
    ],
    'expected' => [
        'billrun' => [
            'invoice_id' => 102,
            'billrun_key' => '201806',
            'aid' => 5,
        ],
    ],
];
    }
}
