<?php

class Test_Case_3439_48 {
    public function test_case() {
        return [
    'test' => [
        'test_number' => 483439,
        'aid' => 12013439,
        'sid' => 2013439,
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
                12013439,
            ],
        ],
    ],
    'expected' => [
        'billrun' => [
            'billrun_key' => '202004',
            'aid' => 12013439,
            'after_vat' => [
                2013439 => 74.72903225805,
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
    'duplicate' => true,
];
    }
}
