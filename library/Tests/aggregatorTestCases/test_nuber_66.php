<?php

class Test_Case_66 {
    public function test_case() {
        return [
    'test' => [
        'test_number' => 66,
        'aid' => 187501,
        'sid' => 187500,
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
                187501,
            ],
        ],
    ],
    'expected' => [
        'billrun' => [
            'billrun_key' => '202008',
            'aid' => 187501,
            'after_vat' => [
                187500 => 113.22580645161288,
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
];
    }
}
