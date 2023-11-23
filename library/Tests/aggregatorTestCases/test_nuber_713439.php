<?php

class Test_Case_713439 {
    public function test_case() {
        return [
    'test' => [
        'test_number' => 713439,
        'aid' => 179753439,
        'sid' => 179763439,
        'function' => [
            'basicCompare',
            'subsPrice',
            'lineExists',
            'linesVSbillrun',
            'rounded',
        ],
        'options' => [
            'stamp' => '202101',
            'force_accounts' => [
                179753439,
            ],
        ],
    ],
    'expected' => [
        'billrun' => [
            'billrun_key' => '202101',
            'aid' => 179753439,
            'after_vat' => [
                179763439 => 160.0258064516129,
            ],
            'total' => 160.0258064516129,
            'vatable' => 136.7741935483871,
            'vat' => 0,
        ],
    ],
    'line' => [
        'types' => [
            'flat',
            'credit',
        ],
    ],
    'jiraLink' => 'https://billrun.atlassian.net/browse/BRCD-2988',
    'duplicate' => true,
];
    }
}
