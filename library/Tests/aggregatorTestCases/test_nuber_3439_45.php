<?php

class Test_Case_3439_45 {
    public function test_case() {
        return [
    'test' => [
        'test_number' => 453439,
        'aid' => 15003439,
        'sid' => 5003439,
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
                15003439,
            ],
        ],
    ],
    'expected' => [
        'billrun' => [
            'billrun_key' => '202004',
            'aid' => 15003439,
            'after_vat' => [
                5003439 => 105.3,
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
    'duplicate' => true,
];
    }
}
