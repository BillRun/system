<?php

class Test_Case_72 {
    public function test_case() {
        return [
    'test' => [
        'test_number' => 72,
        'aid' => 725,
        'sid' => 825,
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
                725,
            ],
        ],
    ],
    'expected' => [
        'billrun' => [
            'billrun_key' => '202102',
            'aid' => 725,
            'after_vat' => [
                825 => 213.61935483870974,
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
];
    }
}
