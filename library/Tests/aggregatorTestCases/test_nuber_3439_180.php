<?php

class Test_Case_3439_180 {
    public function test_case() {
        return [
    'test' => [
        'test_number' => 1803439,
        'aid' => 8853439,
        'sid' => 7753439,
        'function' => [
            'basicCompare',
            'lineExists',
            'linesVSbillrun',
            'rounded',
        ],
        'options' => [
            'stamp' => '202108',
            'force_accounts' => [
                8853439,
            ],
        ],
    ],
    'expected' => [
        'billrun' => [
            'billrun_key' => '202108',
            'aid' => 8853439,
            'after_vat' => [
                7753439 => 0,
            ],
            'total' => 0,
            'vatable' => 0,
            'vat' => 0,
        ],
        'line' => [
            'types' => [
                'flat',
                'credit',
            ],
        ],
    ],
    'duplicate' => true,
];
    }
}
