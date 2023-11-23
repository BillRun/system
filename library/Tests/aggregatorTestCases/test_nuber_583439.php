<?php

class Test_Case_583439 {
    public function test_case() {
        return [
    'test' => [
        'test_number' => 583439,
        'aid' => 16023439,
        'sid' => 6023439,
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
                16023439,
            ],
        ],
    ],
    'expected' => [
        'billrun' => [
            'billrun_key' => '202004',
            'aid' => 16023439,
            'after_vat' => [
                6023439 => 37.36451703,
            ],
            'total' => 37.36451703,
            'vatable' => 31.935483864,
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
