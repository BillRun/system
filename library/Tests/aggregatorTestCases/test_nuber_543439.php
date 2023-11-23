<?php

class Test_Case_543439 {
    public function test_case() {
        return [
    'test' => [
        'test_number' => 543439,
        'aid' => 12023439,
        'sid' => 2023439,
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
                12023439,
            ],
        ],
    ],
    'expected' => [
        'billrun' => [
            'billrun_key' => '202004',
            'aid' => 12023439,
            'after_vat' => [
                2023439 => 74.729033,
            ],
            'total' => 74.729033,
            'vatable' => 63.8709668,
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
