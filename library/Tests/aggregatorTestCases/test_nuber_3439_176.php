<?php

class Test_Case_3439_176 {
    public function test_case() {
        return [
    'test' => [
        'test_number' => 1763439,
        'aid' => 7703439,
        'sid' => 7713439,
        'function' => [
            'basicCompare',
            'lineExists',
            'linesVSbillrun',
            'rounded',
        ],
        'options' => [
            'stamp' => '202108',
            'force_accounts' => [
                7703439,
            ],
        ],
    ],
    'expected' => [
        'billrun' => [
            'billrun_key' => '202108',
            'aid' => 7703439,
            'after_vat' => [
                7713439 => 58.5,
            ],
            'total' => 58.5,
            'vatable' => 50,
            'vat' => 17,
        ],
        'line' => [
            'types' => [
                'flat',
            ],
        ],
    ],
    'duplicate' => true,
];
    }
}
