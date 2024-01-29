<?php

class Test_Case_34 {
    public function test_case() {
        return [
    'test' => [
        'test_number' => 34,
        'aid' => 70,
        'sid' => 71,
        'function' => [
            'totalsPrice',
        ],
        'options' => [
            'stamp' => '201901',
            'force_accounts' => [
                70,
            ],
        ],
    ],
    'expected' => [
        'billrun' => [
            'billrun_key' => '201901',
            'aid' => 70,
            'after_vat' => [
                71 => 117,
            ],
            'total' => 117,
            'vatable' => 100,
            'vat' => 17,
        ],
    ],
];
    }
}
