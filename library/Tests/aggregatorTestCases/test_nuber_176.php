<?php

class Test_Case_176 {
    public function test_case() {
        return [
    'test' => [
        'test_number' => 176,
        'aid' => 770,
        'sid' => 771,
        'function' => [
            'basicCompare',
            'lineExists',
            'linesVSbillrun',
            'rounded',
        ],
        'options' => [
            'stamp' => '202108',
            'force_accounts' => [
                770,
            ],
        ],
    ],
    'expected' => [
        'billrun' => [
            'billrun_key' => '202108',
            'aid' => 770,
            'after_vat' => [
                771 => 58.5,
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
];
    }
}
