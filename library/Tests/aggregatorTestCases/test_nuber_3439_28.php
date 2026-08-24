<?php

class Test_Case_3439_28 {
    public function test_case() {
        return [
    'test' => [
        'test_number' => 283439,
        'aid' => 623439,
        'sid' => 633439,
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
                623439,
            ],
        ],
    ],
    'expected' => [
        'billrun' => [
            'invoice_id' => 125,
            'billrun_key' => '201807',
            'aid' => 623439,
            'after_vat' => [
                633439 => 2457,
            ],
            'total' => 2457,
            'vatable' => 2100,
            'vat' => 17,
        ],
        'line' => [
            'types' => [
                'flat',
                'service',
            ],
        ],
    ],
    'duplicate' => true,
];
    }
}
