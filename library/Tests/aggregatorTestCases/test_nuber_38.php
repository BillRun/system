<?php

class Test_Case_38 {
    public function test_case() {
        return [
    'test' => [
        'test_number' => 38,
        'aid' => 79,
        'sid' => 78,
        'function' => [
            'basicCompare',
            'subsPrice',
            'lineExists',
            'linesVSbillrun',
            'rounded',
        ],
        'options' => [
            'stamp' => '201903',
            'force_accounts' => [
                79,
            ],
        ],
    ],
    'expected' => [
        'billrun' => [
            'invoice_id' => 134,
            'billrun_key' => '201903',
            'aid' => 79,
            'after_vat' => [
                78 => 117,
            ],
        ],
    ],
    'line' => [
        'types' => [
            'flat',
            'service',
        ],
    ],
    'jiraLink' => 'https://billrun.atlassian.net/browse/BRCD-1758',
];
    }
}
