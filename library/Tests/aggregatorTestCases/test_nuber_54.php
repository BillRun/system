<?php

class Test_Case_54 {
    public function test_case() {
        return [
    'test' => [
        'test_number' => 54,
        'aid' => 1202,
        'sid' => 202,
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
                1202,
            ],
        ],
    ],
    'expected' => [
        'billrun' => [
            'billrun_key' => '202004',
            'aid' => 1202,
            'after_vat' => [
                202 => 33.96774,
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
