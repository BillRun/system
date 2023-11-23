<?php

class Test_Case_1833439 {
    public function test_case() {
        return [
    'test' => [
        'test_number' => 1833439,
        'aid' => 8883439,
        'sid' => 7783439,
        'function' => [
            'basicCompare',
            'lineExists',
            'linesVSbillrun',
            'rounded',
        ],
        'options' => [
            'stamp' => '202108',
            'force_accounts' => [
                8883439,
            ],
        ],
    ],
    'expected' => [
        'billrun' => [
            'billrun_key' => '202108',
            'aid' => 8883439,
            'after_vat' => [
                7783439 => 5.85,
            ],
            'total' => 5.85,
            'vatable' => 5,
            'vat' => 17,
        ],
        'line' => [
            'types' => [
                'flat',
                'service',
            ],
        ],
    ],
    'duplicate' => true,
];
    }
}
