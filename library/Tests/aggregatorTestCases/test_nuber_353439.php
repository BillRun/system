<?php

class Test_Case_353439 {
    public function test_case() {
        return [
    'test' => [
        'test_number' => 353439,
        'aid' => 733439,
        'sid' => 743439,
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
                733439,
            ],
        ],
    ],
    'expected' => [
        'billrun' => [
            'invoice_id' => 131,
            'billrun_key' => '201901',
            'aid' => 733439,
            'after_vat' => [
                743439 => 117,
            ],
        ],
    ],
    'line' => [
        'types' => [
            'flat',
        ],
    ],
    'jiraLink' => 'https://billrun.atlassian.net/browse/BRCD-1725',
    'duplicate' => true,
];
    }
}
