<?php

class Test_Case_15 {
    public function test_case() {
        return [
    'test' => [
        'test_number' => 15,
        'aid' => 34,
        'sid' => 33,
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
                34,
            ],
        ],
    ],
    'expected' => [
        'billrun' => [
            'invoice_id' => 113,
            'billrun_key' => '201806',
            'aid' => 34,
            'after_vat' => [
                33 => 117,
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
];
    }
}
