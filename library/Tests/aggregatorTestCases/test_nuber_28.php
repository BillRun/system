<?php

class Test_Case_28 {
    public function test_case() {
        return [
    'test' => [
        'test_number' => 28,
        'aid' => 62,
        'sid' => 63,
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
                62,
            ],
        ],
    ],
    'expected' => [
        'billrun' => [
            'billrun_key' => '201807',
            'aid' => 62,
            'after_vat' => [
                63 => 2457,
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
];
    }
}
