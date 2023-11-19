<?php

class Test_Case_9 {
    public function test_case() {
        return [
    'test' => [
        'test_number' => 9,
        'aid' => 19,
        'sid' => 20,
        'function' => [
            'basicCompare',
            'totalsPrice',
            'lineExists',
            'linesVSbillrun',
            'rounded',
        ],
        'options' => [
            'force_accounts' => [
                19,
            ],
        ],
    ],
    'expected' => [
        'billrun' => [
            'invoice_id' => 107,
            'billrun_key' => '201806',
            'aid' => 19,
            'after_vat' => [
                20 => 47.554838711,
            ],
            'total' => 47.554838711,
            'vatable' => 40.64516129032258,
            'vat' => 17,
        ],
        'line' => [
            'types' => [
                'flat',
                'credit',
            ],
        ],
    ],
    'postRun' => [
        'saveId',
    ],
];
    }
}
