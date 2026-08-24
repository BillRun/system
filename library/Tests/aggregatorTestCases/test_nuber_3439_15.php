<?php

class Test_Case_3439_15 {
    public function test_case() {
        return [
    'test' => [
        'test_number' => 153439,
        'aid' => 343439,
        'sid' => 333439,
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
                343439,
            ],
        ],
    ],
    'expected' => [
        'billrun' => [
            'invoice_id' => 113,
            'billrun_key' => '201806',
            'aid' => 343439,
            'after_vat' => [
                333439 => 117,
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
    'duplicate' => true,
];
    }
}
