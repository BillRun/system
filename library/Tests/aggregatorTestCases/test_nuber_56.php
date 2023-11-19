<?php

class Test_Case_56 {
    public function test_case() {
        return [
    'test' => [
        'test_number' => 56,
        'aid' => 1402,
        'sid' => 402,
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
                1402,
            ],
        ],
    ],
    'expected' => [
        'billrun' => [
            'billrun_key' => '202004',
            'aid' => 1402,
            'after_vat' => [
                402 => 33.96774,
            ],
            'total' => 33.96774,
            'vatable' => 29.032258,
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
