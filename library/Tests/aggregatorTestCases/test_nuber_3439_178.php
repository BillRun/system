<?php

class Test_Case_3439_178 {
    public function test_case() {
        return [
    'test' => [
        'test_number' => 1783439,
        'aid' => 8833439,
        'sid' => 7733439,
        'function' => [
            'basicCompare',
            'lineExists',
            'linesVSbillrun',
            'rounded',
        ],
        'options' => [
            'stamp' => '202108',
            'force_accounts' => [
                8833439,
            ],
        ],
    ],
    'expected' => [
        'billrun' => [
            'billrun_key' => '202108',
            'aid' => 8833439,
            'after_vat' => [
                7733439 => 117,
            ],
            'total' => 117,
            'vatable' => 100,
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
