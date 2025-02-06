<?php

class Test_Case_3439_37 {
    public function test_case() {
        return [
    'test' => [
        'test_number' => 373439,
        'aid' => 753439,
        'sid' => 763439,
        'function' => [
            'planExist',
            'basicCompare',
            'totalsPrice',
            'lineExists',
            'linesVSbillrun',
            'rounded',
        ],
        'options' => [
            'stamp' => '201808',
            'force_accounts' => [
                753439,
            ],
        ],
    ],
    'expected' => [
        'billrun' => [
            'invoice_id' => 133,
            'billrun_key' => '201808',
            'aid' => 753439,
            'after_vat' => [
                763439 => 117,
            ],
            'total' => 117,
            'vatable' => 100,
            'vat' => 17,
        ],
        'line' => [
            'types' => [
                'flat',
            ],
        ],
    ],
    'postRun' => [
        'saveId',
    ],
    'jiraLink' => [
        'https://billrun.atlassian.net/browse/BRCD-1725',
        'https://billrun.atlassian.net/browse/BRCD-1730',
    ],
    'duplicate' => true,
];
    }
}
