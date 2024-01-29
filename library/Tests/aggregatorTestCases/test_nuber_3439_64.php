<?php

class Test_Case_3439_64 {
    public function test_case() {
        return [
    'test' => [
        'test_number' => 643439,
        'aid' => 16033439,
        'sid' => 6033439,
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
                16033439,
            ],
        ],
    ],
    'expected' => [
        'billrun' => [
            'billrun_key' => '202004',
            'aid' => 16033439,
            'after_vat' => [
                6033439 => 40.76129119,
            ],
            'total' => 40.76129119,
            'vatable' => 34.83870926,
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
