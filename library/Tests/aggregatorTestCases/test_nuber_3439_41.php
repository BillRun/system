<?php

class Test_Case_3439_41 {
    public function test_case() {
        return [
    'test' => [
        'test_number' => 413439,
        'aid' => 263439,
        'function' => [
            'basicCompare',
            'linesVSbillrun',
            'lineExists',
            'rounded',
            'passthrough',
            'totalsPrice',
        ],
        'options' => [
            'stamp' => '202003',
            'force_accounts' => [
                263439,
            ],
        ],
    ],
    'expected' => [
        'billrun' => [
            'invoice_id' => 137,
            'billrun_key' => '202003',
            'aid' => 263439,
            'after_vat' => [
                1003439 => 245.7,
            ],
            'total' => 245.7,
            'vatable' => 210,
            'vat' => 17,
        ],
    ],
    'line' => [
        'types' => [
            'flat',
            'service',
        ],
    ],
    'jiraLink' => 'https://billrun.atlassian.net/browse/BRCD-1493',
    'duplicate' => true,
];
    }
}
