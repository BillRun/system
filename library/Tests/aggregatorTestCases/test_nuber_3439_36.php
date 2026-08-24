<?php

class Test_Case_3439_36 {
    public function test_case() {
        return [
    'test' => [
        'test_number' => 363439,
        'aid' => 753439,
        'sid' => 763439,
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
                753439,
            ],
        ],
    ],
    'expected' => [
        'billrun' => [
            'invoice_id' => 132,
            'billrun_key' => '201807',
            'aid' => 753439,
            'after_vat' => [
                763439 => 234,
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
    'duplicate' => true,
];
    }
}
