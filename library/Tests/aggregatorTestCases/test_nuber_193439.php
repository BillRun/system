<?php

class Test_Case_193439 {
    public function test_case() {
        return [
    'test' => [
        'test_number' => 193439,
        'aid' => 423439,
        'sid' => 433439,
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
                423439,
            ],
        ],
    ],
    'expected' => [
        'billrun' => [
            'invoice_id' => 117,
            'billrun_key' => '201806',
            'aid' => 423439,
            'after_vat' => [
                433439 => 234,
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
