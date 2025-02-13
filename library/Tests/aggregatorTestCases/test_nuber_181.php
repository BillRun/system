<?php

class Test_Case_181 {
    public function test_case() {
        return [
    'test' => [
        'test_number' => 181,
        'aid' => 886,
        'sid' => 776,
        'function' => [
            'basicCompare',
            'lineExists',
            'linesVSbillrun',
            'rounded',
        ],
        'options' => [
            'stamp' => '202108',
            'force_accounts' => [
                886,
            ],
        ],
    ],
    'expected' => [
        'billrun' => [
            'billrun_key' => '202108',
            'aid' => 886,
            'after_vat' => [
                776 => 0,
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
];
    }
}
