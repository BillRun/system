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
                401 => 74.72903225806452,
            ],
            'total' => 74.72903225806452,
            'vatable' => 63.87096774193549,
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
