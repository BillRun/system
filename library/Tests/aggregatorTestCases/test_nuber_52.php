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
                601 => 74.72903225805,
            ],
            'total' => 74.72903225805,
            'vatable' => 63.8709677424,
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
