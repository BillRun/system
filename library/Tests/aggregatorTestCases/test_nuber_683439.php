<?php

class Test_Case_683439 {
    public function test_case() {
        return [
    'test' => [
        'test_number' => 683439,
        'aid' => 17703439,
        'sid' => 17713439,
        'function' => [
            'basicCompare',
            'subsPrice',
            'lineExists',
            'linesVSbillrun',
            'rounded',
        ],
        'options' => [
            'stamp' => '202005',
            'force_accounts' => [
                17703439,
            ],
        ],
    ],
    'expected' => [
        'billrun' => [
            'billrun_key' => '202005',
            'aid' => 17703439,
            'after_vat' => [
                17713439 => 200,
            ],
            'total' => 200,
            'vatable' => 200,
            'vat' => 0,
        ],
    ],
    'line' => [
        'types' => [
            'flat',
            'service',
        ],
    ],
    'jiraLink' => 'https://billrun.atlassian.net/browse/BRCD-2492',
    'duplicate' => true,
];
    }
}
