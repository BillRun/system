<?php

class Test_Case_183439 {
    public function test_case() {
        return [
    'test' => [
        'test_number' => 183439,
        'aid' => 403439,
        'sid' => 413439,
        'function' => [
            'basicCompare',
            'totalsPrice',
            'lineExists',
            'linesVSbillrun',
            'rounded',
        ],
        'options' => [
            'stamp' => '201806',
            'force_accounts' => [
                403439,
            ],
        ],
    ],
    'expected' => [
        'billrun' => [
            'invoice_id' => 116,
            'billrun_key' => '201806',
            'aid' => 403439,
            'after_vat' => [
                413439 => 351,
            ],
            'total' => 351,
            'vatable' => 300,
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
