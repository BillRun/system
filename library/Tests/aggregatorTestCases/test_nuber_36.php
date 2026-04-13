<?php

class Test_Case_36 {
    public function test_case() {
        return [
    'test' => [
        'test_number' => 36,
        'aid' => 75,
        'sid' => 76,
        'function' => [
            'basicCompare',
            'totalsPrice',
            'lineExists',
            'linesVSbillrun',
            'rounded',
        ],
        'options' => [
            'stamp' => '201807',
            'force_accounts' => [
                75,
            ],
        ],
    ],
    'expected' => [
        'billrun' => [
            'invoice_id' => 132,
            'billrun_key' => '201807',
            'aid' => 75,
            'after_vat' => [
                76 => 234,
            ],
            'total' => 234,
            'vatable' => 200,
            'vat' => 17,
        ],
        'line' => [
            'types' => [
                'flat',
                'service',
            ],
        ],
    ],
    'jiraLink' => 'https://billrun.atlassian.net/browse/BRCD-1725',
];
    }
}
