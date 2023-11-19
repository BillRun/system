<?php

class Test_Case_41 {
    public function test_case() {
        return [
    'test' => [
        'test_number' => 41,
        'aid' => 26,
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
                26,
            ],
        ],
    ],
    'expected' => [
        'billrun' => [
            'billrun_key' => '202003',
            'aid' => 26,
            'after_vat' => [
                100 => 245.7,
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
];
    }
}
