<?php

class Test_Case_60 {
    public function test_case() {
        return [
    'test' => [
        'test_number' => 60,
        'aid' => 1203,
        'sid' => 203,
        'function' => [
            'basicCompare',
            'totalsPrice',
            'lineExists',
            'linesVSbillrun',
            'rounded',
        ],
        'options' => [
            'stamp' => '202004',
            'force_accounts' => [
                1203,
            ],
        ],
    ],
    'expected' => [
        'billrun' => [
            'billrun_key' => '202004',
            'aid' => 1203,
            'after_vat' => [
                203 => 75.1064516,
            ],
            'total' => 75.1064516,
            'vatable' => 64.1935483,
            'vat' => 17,
        ],
        'line' => [
            'types' => [
                'flat',
                'credit',
            ],
        ],
    ],
    'jiraLink' => 'https://billrun.atlassian.net/browse/BRCD-1913',
];
    }
}
