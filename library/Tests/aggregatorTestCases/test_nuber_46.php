<?php

class Test_Case_46 {
    public function test_case() {
        return [
    'test' => [
        'test_number' => 46,
        'aid' => 1600,
        'sid' => 600,
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
                1600,
            ],
        ],
    ],
    'expected' => [
        'billrun' => [
            'billrun_key' => '202004',
            'aid' => 1600,
            'after_vat' => [
                600 => 105.3,
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
