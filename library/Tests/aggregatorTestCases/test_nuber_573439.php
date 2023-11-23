<?php

class Test_Case_573439 {
    public function test_case() {
        return [
    'test' => [
        'test_number' => 573439,
        'aid' => 15023439,
        'sid' => 5023439,
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
                15023439,
            ],
        ],
    ],
    'expected' => [
        'billrun' => [
            'billrun_key' => '202004',
            'aid' => 15023439,
            'after_vat' => [
                5023439 => 98.50645,
            ],
            'total' => 98.50645,
            'vatable' => 84.193548,
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
