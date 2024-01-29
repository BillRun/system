<?php

class Test_Case_35 {
    public function test_case() {
        return [
    'test' => [
        'test_number' => 35,
        'aid' => 73,
        'sid' => 74,
        'function' => [
            'basicCompare',
            'subsPrice',
            'lineExists',
            'linesVSbillrun',
            'rounded',
        ],
        'options' => [
            'stamp' => '201901',
            'force_accounts' => [
                73,
            ],
        ],
    ],
    'expected' => [
        'billrun' => [
            'invoice_id' => 131,
            'billrun_key' => '201901',
            'aid' => 73,
            'after_vat' => [
                74 => 117,
            ],
        ],
    ],
    'line' => [
        'types' => [
            'flat',
        ],
    ],
    'jiraLink' => 'https://billrun.atlassian.net/browse/BRCD-1725',
];
    }
}
