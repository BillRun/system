<?php

class Test_Case_3439_66 {
    public function test_case() {
        return [
    'test' => [
        'test_number' => 663439,
        'aid' => 1875013439,
        'sid' => 1875003439,
        'function' => [
            'basicCompare',
            'totalsPrice',
            'lineExists',
            'linesVSbillrun',
            'rounded',
        ],
        'options' => [
            'stamp' => '202008',
            'force_accounts' => [
                1875013439,
            ],
        ],
    ],
    'expected' => [
        'billrun' => [
            'billrun_key' => '202008',
            'aid' => 1875013439,
            'after_vat' => [
                1875003439 => 113.22580645161288,
            ],
            'total' => 113.22580645161288,
            'vatable' => 96.77419354838709,
            'vat' => 17,
        ],
        'line' => [
            'types' => [
                'flat',
            ],
        ],
    ],
    'jiraLink' => 'https://billrun.atlassian.net/browse/BRCD-2742',
    'duplicate' => true,
];
    }
}
