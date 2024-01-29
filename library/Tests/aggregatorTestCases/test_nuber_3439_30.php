<?php

class Test_Case_3439_30 {
    public function test_case() {
        return [
    'test' => [
        'test_number' => 303439,
        'aid' => 643439,
        'sid' => 653439,
        'function' => [
            'basicCompare',
            'totalsPrice',
            'lineExists',
            'linesVSbillrun',
            'rounded',
        ],
        'options' => [
            'stamp' => '201807',
            'force_accounts' => [
                643439,
            ],
        ],
    ],
    'expected' => [
        'billrun' => [
            'invoice_id' => 127,
            'billrun_key' => '201807',
            'aid' => 643439,
            'after_vat' => [
                653439 => 89.7,
            ],
            'total' => 89.7,
            'vatable' => 76.666666667,
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
