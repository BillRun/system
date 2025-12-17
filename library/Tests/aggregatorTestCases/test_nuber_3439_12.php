<?php

class Test_Case_3439_12 {
    public function test_case() {
        return [
    'test' => [
        'test_number' => 123439,
        'aid' => 273439,
        'sid' => [
            28,
            29,
        ],
        'function' => [
            'basicCompare',
            'sumSids',
            'subsPrice',
            'totalsPrice',
            'lineExists',
            'linesVSbillrun',
            'rounded',
        ],
        'options' => [
            'stamp' => '201806',
            'force_accounts' => [
                273439,
            ],
        ],
    ],
    'expected' => [
        'billrun' => [
            'invoice_id' => 110,
            'billrun_key' => '201806',
            'aid' => 273439,
            'after_vat' => [
                283439 => 117,
                293439 => 52.8387096774193,
            ],
            'total' => 169.838709677,
            'vatable' => 145.1612903225806,
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
