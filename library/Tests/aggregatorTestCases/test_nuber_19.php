<?php

class Test_Case_19 {
    public function test_case() {
        return [
    'test' => [
        'test_number' => 19,
        'aid' => 42,
        'sid' => 43,
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
                42,
            ],
        ],
    ],
    'expected' => [
        'billrun' => [
            'invoice_id' => 117,
            'billrun_key' => '201806',
            'aid' => 42,
            'after_vat' => [
                43 => 234,
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
];
    }
}
