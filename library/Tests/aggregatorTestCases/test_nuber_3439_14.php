<?php

class Test_Case_3439_14 {
    public function test_case() {
        return [
    'test' => [
        'test_number' => 143439,
        'aid' => 323439,
        'sid' => 333439,
        'function' => [
            'basicCompare',
            'totalsPrice',
            'lineExists',
            'linesVSbillrun',
            'rounded',
        ],
        'options' => [
            'force_accounts' => [
                323439,
            ],
        ],
    ],
    'expected' => [
        'billrun' => [
            'invoice_id' => 112,
            'billrun_key' => '201806',
            'aid' => 323439,
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
    'duplicate' => true,
];
    }
}
