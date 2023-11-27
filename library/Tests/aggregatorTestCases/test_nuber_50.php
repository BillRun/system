<?php

class Test_Case_50 {
    public function test_case() {
        return [
    'test' => [
        'test_number' => 50,
        'aid' => 1401,
        'sid' => 401,
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
                1401,
            ],
        ],
    ],
    'expected' => [
        'billrun' => [
            'billrun_key' => '202004',
            'aid' => 1401,
            'after_vat' => [
                401 => 75.1064516,
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
