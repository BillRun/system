<?php

class Test_Case_68 {
    public function test_case() {
        return [
    'test' => [
        'test_number' => 68,
        'aid' => 1770,
        'sid' => 1771,
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
                1770,
            ],
        ],
    ],
    'expected' => [
        'billrun' => [
            'billrun_key' => '202005',
            'aid' => 1770,
            'after_vat' => [
                1771 => 200,
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
];
    }
}
