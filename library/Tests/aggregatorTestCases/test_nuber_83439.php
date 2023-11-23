<?php

class Test_Case_83439 {
    public function test_case() {
        return [
    'test' => [
        'test_number' => 83439,
        'aid' => 133439,
        'sid' => [
            15,
            16,
        ],
        'function' => [
            'basicCompare',
            'sumSids',
            'totalsPrice',
            'lineExists',
            'linesVSbillrun',
            'rounded',
        ],
        'options' => [
            'stamp' => '201806',
            'force_accounts' => [
                133439,
            ],
        ],
    ],
    'expected' => [
        'billrun' => [
            'invoice_id' => 106,
            'billrun_key' => '201806',
            'aid' => 133439,
            'after_vat' => [
                153439 => 117,
                163439 => 117,
            ],
            'total' => 234,
            'vatable' => 200,
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
