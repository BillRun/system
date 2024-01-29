<?php

class Test_Case_3439_5 {
    public function test_case() {
        return [
    'test' => [
        'test_number' => 53439,
        'aid' => 73439,
        'function' => [
            'basicCompare',
            'linesVSbillrun',
            'rounded',
            'passthrough',
        ],
        'options' => [
            'stamp' => '201805',
            'force_accounts' => [
                73439,
            ],
        ],
    ],
    'expected' => [
        'billrun' => [
            'invoice_id' => 103,
            'billrun_key' => '201805',
            'aid' => 73439,
        ],
    ],
    'duplicate' => true,
];
    }
}
