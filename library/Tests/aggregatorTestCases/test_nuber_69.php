<?php

class Test_Case_69 {
    public function test_case() {
        return [
    'test' => [
        'test_number' => 69,
        'aid' => 2000000784,
        'sid' => 290,
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
                2000000784,
            ],
        ],
    ],
    'expected' => [
        'billrun' => [
            'billrun_key' => '202101',
            'aid' => 2000000784,
            'after_vat' => [
                290 => 29.08097389230968,
                0 => 1.9565442,
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
];
    }
}
