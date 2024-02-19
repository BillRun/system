<?php

class Test_Case_70 {
    public function test_case() {
        return [
    'test' => [
        'test_number' => 70,
        'aid' => 2000000785,
        'sid' => 291,
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
                2000000785,
            ],
        ],
    ],
    'expected' => [
        'billrun' => [
            'billrun_key' => '202101',
            'aid' => 2000000785,
            'after_vat' => [
                291 => 7.586449083870966,
                0 => 1.9565442,
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
];
    }
}
