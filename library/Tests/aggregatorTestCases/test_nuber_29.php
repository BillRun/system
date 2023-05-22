<?php

class Test_Case_29 {
    public function test_case() {
        return [
    'test' => [
        'test_number' => 29,
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
            'stamp' => '201808',
            'force_accounts' => [
                62,
            ],
        ],
    ],
    'expected' => [
        'billrun' => [
            'billrun_key' => '201808',
            'aid' => 62,
            'after_vat' => [
                63 => 117,
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
];
    }
}
