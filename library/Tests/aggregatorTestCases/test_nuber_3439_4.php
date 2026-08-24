<?php

class Test_Case_3439_4 {
    public function test_case() {
        return [
    'test' => [
        'test_number' => 43439,
        'aid' => 53439,
        'function' => [
            'basicCompare',
            'linesVSbillrun',
            'rounded',
            'passthrough',
        ],
        'options' => [
            'force_accounts' => [
                53439,
            ],
        ],
    ],
    'expected' => [
        'billrun' => [
            'invoice_id' => 102,
            'billrun_key' => '201806',
            'aid' => 53439,
        ],
    ],
    'duplicate' => true,
];
    }
}
