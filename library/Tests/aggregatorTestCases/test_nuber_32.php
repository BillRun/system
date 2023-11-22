<?php

class Test_Case_32 {
    public function test_case() {
        return [
    'test' => [
        'test_number' => 32,
        'aid' => 1,
        'sid' => 2,
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
                1,
            ],
        ],
    ],
    'expected' => [
        'billrun' => [
            'billrun_key' => '201807',
            'aid' => 1,
            'after_vat' => [
                2 => 307,
            ],
            'total' => 307,
            'vatable' => 100,
            'vat' => 17,
        ],
        'line' => [
            'types' => [
                'flat',
                'non',
                'credit',
                'service',
            ],
        ],
    ],
];
    }
}
