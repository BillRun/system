<?php

class Test_Case_185 {
    public function test_case() {
        return [
    'test' => [
        'test_number' => 185,
        'aid' => 890,
        'sid' => 780,
        'function' => [
            'basicCompare',
            'lineExists',
            'linesVSbillrun',
            'rounded',
        ],
        'options' => [
            'stamp' => '202108',
            'force_accounts' => [
                890,
            ],
        ],
    ],
    'expected' => [
        'billrun' => [
            'billrun_key' => '202108',
            'aid' => 890,
            'after_vat' => [
                780 => 117,
            ],
            'total' => 117,
            'vatable' => 100,
            'vat' => 17,
        ],
        'line' => [
            'types' => [
                'flat',
                'service',
            ],
        ],
    ],
];
    }
}
