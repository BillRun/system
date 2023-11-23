<?php

class Test_Case_703439 {
    public function test_case() {
        return [
    'test' => [
        'test_number' => 703439,
        'aid' => 20000007853439,
        'sid' => 2913439,
        'function' => [
            'basicCompare',
            'subsPrice',
            'lineExists',
            'linesVSbillrun',
            'rounded',
        ],
        'options' => [
            'stamp' => '202101',
            'force_accounts' => [
                20000007853439,
            ],
        ],
    ],
    'expected' => [
        'billrun' => [
            'billrun_key' => '202101',
            'aid' => 20000007853439,
            'after_vat' => [
                3439 => 1.9565442,
                2913439 => 7.586449083870966,
            ],
            'total' => 9.542993283870967,
            'vatable' => 7.5594141935483865,
            'vat' => 0,
        ],
    ],
    'line' => [
        'types' => [
            'flat',
            'service',
            'credit',
        ],
    ],
    'jiraLink' => 'https://billrun.atlassian.net/browse/BRCD-2993',
    'duplicate' => true,
];
    }
}
