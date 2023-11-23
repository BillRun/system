<?php

class Test_Case_1813439 {
    public function test_case() {
        return [
    'test' => [
        'test_number' => 1813439,
        'aid' => 8863439,
        'sid' => 7763439,
        'function' => [
            'basicCompare',
            'lineExists',
            'linesVSbillrun',
            'rounded',
        ],
        'options' => [
            'stamp' => '202108',
            'force_accounts' => [
                8863439,
            ],
        ],
    ],
    'expected' => [
        'billrun' => [
            'billrun_key' => '202108',
            'aid' => 8863439,
            'after_vat' => [
                7763439 => 0,
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
