<?php

class Test_Case_45 {
    public function test_case() {
        return [
    'test' => [
        'test_number' => 45,
        'aid' => 1500,
        'sid' => 500,
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
                1500,
            ],
        ],
    ],
    'expected' => [
        'billrun' => [
            'billrun_key' => '202004',
            'aid' => 1500,
            'after_vat' => [
                500 => 105.3,
            ],
            'total' => 105.3,
            'vatable' => 90,
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
