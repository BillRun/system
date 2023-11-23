<?php

class Test_Case_93439 {
    public function test_case() {
        return [
    'test' => [
        'test_number' => 93439,
        'aid' => 193439,
        'sid' => 203439,
        'function' => [
            'basicCompare',
            'totalsPrice',
            'lineExists',
            'linesVSbillrun',
            'rounded',
        ],
        'options' => [
            'force_accounts' => [
                193439,
            ],
        ],
    ],
    'expected' => [
        'billrun' => [
            'invoice_id' => 107,
            'billrun_key' => '201806',
            'aid' => 193439,
            'after_vat' => [
                203439 => 47.554838711,
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
    'duplicate' => true,
];
    }
}
