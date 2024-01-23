<?php

class Test_Case_3439_177 {
    public function test_case() {
        return [
    'test' => [
        'test_number' => 1773439,
        'aid' => 8823439,
        'sid' => 7723439,
        'function' => [
            'basicCompare',
            'lineExists',
            'linesVSbillrun',
            'rounded',
        ],
        'options' => [
            'stamp' => '202108',
            'force_accounts' => [
                8823439,
            ],
        ],
    ],
    'expected' => [
        'billrun' => [
            'billrun_key' => '202108',
            'aid' => 8823439,
            'after_vat' => [
                7723439 => 58.5,
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
