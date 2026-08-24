<?php

class Test_Case_3439_38 {
    public function test_case() {
        return [
    'test' => [
        'test_number' => 383439,
        'aid' => 793439,
        'sid' => 783439,
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
                793439,
            ],
        ],
    ],
    'expected' => [
        'billrun' => [
            'invoice_id' => 134,
            'billrun_key' => '201903',
            'aid' => 793439,
            'after_vat' => [
                783439 => 117,
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
    'duplicate' => true,
];
    }
}
