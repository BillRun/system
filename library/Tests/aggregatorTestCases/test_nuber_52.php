<?php

class Test_Case_52 {
    public function test_case() {
        return [
    'test' => [
        'test_number' => 52,
        'aid' => 1601,
        'sid' => 601,
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
                1601,
            ],
        ],
    ],
    'expected' => [
        'billrun' => [
            'billrun_key' => '202004',
            'aid' => 1601,
            'after_vat' => [
                601 => 74.72903225806452,
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
