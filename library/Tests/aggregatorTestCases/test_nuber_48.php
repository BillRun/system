<?php

class Test_Case_48 {
    public function test_case() {
        return [
    'test' => [
        'test_number' => 48,
        'aid' => 1201,
        'sid' => 201,
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
                1201,
            ],
        ],
    ],
    'expected' => [
        'billrun' => [
            'billrun_key' => '202004',
            'aid' => 1201,
            'after_vat' => [
                201 => 74.72903225806452,
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
