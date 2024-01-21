<?php

class Test_Case_47 {
    public function test_case() {
        return [
    'test' => [
        'test_number' => 47,
        'aid' => 1700,
        'sid' => 700,
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
                1700,
            ],
        ],
    ],
    'expected' => [
        'billrun' => [
            'billrun_key' => '202004',
            'aid' => 1700,
            'after_vat' => [
                700 => 105.3,
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
