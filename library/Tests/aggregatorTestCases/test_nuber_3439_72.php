<?php

class Test_Case_3439_72 {
    public function test_case() {
        return [
    'test' => [
        'test_number' => 723439,
        'aid' => 7253439,
        'sid' => 8253439,
        'function' => [
            'basicCompare',
            'subsPrice',
            'lineExists',
            'linesVSbillrun',
            'rounded',
        ],
        'options' => [
            'stamp' => '202102',
            'force_accounts' => [
                7253439,
            ],
        ],
    ],
    'expected' => [
        'billrun' => [
            'billrun_key' => '202102',
            'aid' => 7253439,
            'after_vat' => [
                8253439 => 213.61935483870974,
            ],
            'total' => 213.61935483870974,
            'vatable' => 182.5806451612904,
            'vat' => 0,
        ],
    ],
    'line' => [
        'types' => [
            'flat',
            'credit',
        ],
    ],
    'jiraLink' => 'https://billrun.atlassian.net/browse/BRCD-2996',
    'duplicate' => true,
];
    }
}
