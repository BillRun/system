<?php

class Test_Case_5 {
    public function test_case() {
        return [
    'test' => [
        'test_number' => 5,
        'aid' => 7,
        'function' => [
            'basicCompare',
            'linesVSbillrun',
            'rounded',
            'passthrough',
        ],
        'options' => [
            'stamp' => '201805',
            'force_accounts' => [
                7,
            ],
        ],
    ],
    'expected' => [
        'billrun' => [
            'invoice_id' => 103,
            'billrun_key' => '201805',
            'aid' => 7,
        ],
    ],
];
    }
}
