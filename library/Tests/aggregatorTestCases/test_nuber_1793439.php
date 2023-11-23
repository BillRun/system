<?php

class Test_Case_1793439 {
    public function test_case() {
        return [
    'test' => [
        'test_number' => 1793439,
        'aid' => 8843439,
        'sid' => 7743439,
        'function' => [
            'basicCompare',
            'lineExists',
            'linesVSbillrun',
            'rounded',
        ],
        'options' => [
            'stamp' => '202108',
            'force_accounts' => [
                8843439,
            ],
        ],
    ],
    'expected' => [
        'billrun' => [
            'billrun_key' => '202108',
            'aid' => 8843439,
            'after_vat' => [
                7743439 => 60.387096774193544,
            ],
            'total' => 60.387096774193544,
            'vatable' => 51.61290322580645,
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
