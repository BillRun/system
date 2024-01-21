<?php

class Test_Case_58 {
    public function test_case() {
        return [
    'test' => [
        'test_number' => 58,
        'aid' => 1602,
        'sid' => 602,
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
                1602,
            ],
        ],
    ],
    'expected' => [
        'billrun' => [
            'billrun_key' => '202004',
            'aid' => 1602,
            'after_vat' => [
                602 => 37.36451612903226
            ],
            'total' =>37.36451612903226,
            'vatable' =>  31.935483870967744,
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
