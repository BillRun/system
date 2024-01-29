<?php

class Test_Case_3439_34 {
    public function test_case() {
        return [
    'test' => [
        'test_number' => 343439,
        'aid' => 703439,
        'sid' => 713439,
        'function' => [
            'totalsPrice',
        ],
        'options' => [
            'stamp' => '201901',
            'force_accounts' => [
                703439,
            ],
        ],
    ],
    'expected' => [
        'billrun' => [
            'billrun_key' => '201901',
            'aid' => 703439,
            'after_vat' => [
                713439 => 117,
            ],
            'total' => 117,
            'vatable' => 100,
            'vat' => 17,
        ],
    ],
    'duplicate' => true,
];
    }
}
