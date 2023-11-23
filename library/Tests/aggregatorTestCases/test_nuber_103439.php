<?php

class Test_Case_103439 {
    public function test_case() {
        return [
    'test' => [
        'test_number' => 103439,
        'aid' => 213439,
        'sid' => 203439,
        'function' => [
            'checkForeignFileds',
            'basicCompare',
            'totalsPrice',
            'lineExists',
            'linesVSbillrun',
            'rounded',
        ],
        'checkForeignFileds' => [
            'discount' => [
                'foreign.discount.description' => 'ttt',
            ],
        ],
        'options' => [
            'stamp' => '201806',
            'force_accounts' => [
                213439,
            ],
        ],
    ],
    'expected' => [
        'billrun' => [
            'invoice_id' => 108,
            'billrun_key' => '201806',
            'aid' => 213439,
            'after_vat' => [
                213439 => 57.745161289,
            ],
            'total' => 57.745161289,
            'vatable' => 49.354838709677416,
            'vat' => 17,
        ],
        'line' => [
            'types' => [
                'flat',
                'credit',
            ],
        ],
    ],
    'duplicate' => true,
];
    }
}
