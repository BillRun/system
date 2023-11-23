<?php

class Test_Case_133439 {
    public function test_case() {
        return [
    'test' => [
        'test_number' => 133439,
        'aid' => 303439,
        'sid' => 313439,
        'function' => [
            'basicCompare',
            'totalsPrice',
            'lineExists',
            'linesVSbillrun',
            'rounded',
            'passthrough',
        ],
        'options' => [
            'stamp' => '201806',
            'force_accounts' => [
                303439,
            ],
        ],
    ],
    'expected' => [
        'billrun' => [
            'invoice_id' => 111,
            'billrun_key' => '201806',
            'aid' => 303439,
            'after_vat' => [
                313439 => 234,
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
    'postRun' => [
        'saveId',
    ],
    'duplicate' => true,
];
    }
}
