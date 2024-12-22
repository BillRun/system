<?php

class Test_Case_3439_56 {
    public function test_case() {
        return [
    'test' => [
        'test_number' => 563439,
        'aid' => 14023439,
        'sid' => 4023439,
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
                14023439,
            ],
        ],
    ],
    'expected' => [
        'billrun' => [
            'billrun_key' => '202004',
            'aid' => 14023439,
            'after_vat' => [
                4023439 => 75.106451612903,
            ],
            'total' => 75.106451612903,
            'vatable' => 64.19354838709,
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
