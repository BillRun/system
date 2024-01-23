<?php

class Test_Case_71 {
    public function test_case() {
        return [
    'test' => [
        'test_number' => 71,
        'aid' => 17975,
        'sid' => 17976,
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
                17975,
            ],
        ],
    ],
    'expected' => [
        'billrun' => [
            'billrun_key' => '202101',
            'aid' => 17975,
            'after_vat' => [
                17976 => 160.0258064516129,
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
];
    }
}
