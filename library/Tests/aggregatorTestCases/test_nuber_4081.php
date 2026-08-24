<?php

class Test_Case_4081 {
    public function test_case() {
        return [
    'test' => [
        'test_number' => 4081,
        'aid' => 4081,
        'sid' => 40811,
        'function' => [
            'totalsPrice',
        ],
        'options' => [
            'stamp' => '202304',
            'force_accounts' => [
                4081,
            ],
        ],
    ],
    'expected' => [
        'billrun' => [
            'billrun_key' => '202304',
            'aid' => 4081,
            'after_vat' => [
                40811 => 117,
            ],
            'total' => 117,
            'vatable' => 100,
            'vat' => 17,
        ],
        'lines' => [
        ],
    ],
    'duplicate' => false,
];
    }
}
