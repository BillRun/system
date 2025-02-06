<?php

class Test_Case_3439_69 {
    public function test_case() {
        return [
    'test' => [
        'test_number' => 693439,
        'aid' => 20000007843439,
        'sid' => 2903439,
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
                20000007843439,
            ],
        ],
    ],
    'expected' => [
        'billrun' => [
            'billrun_key' => '202101',
            'aid' => 20000007843439,
            'after_vat' => [
                3439 => 1.9565442,
                2903439 => 29.08097389230968,
            ],
            'total' => 30.339039414890326,
            'vatable' => 25.93080291870968,
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
