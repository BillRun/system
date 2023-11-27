<?php

class Test_Case_13595 {
    public function test_case() {
        return [
    'test' => [
        'test_number' => 13595,
        'aid' => 13595,
        'sid' => 63595,
        'function' => [
            'billrunNotCreated',
        ],
        'options' => [
            'stamp' => '202202',
            'force_accounts' => [
                13595,
            ],
        ],
    ],
    'expected' => [
        'billrun' => [
        ],
        'lines' => [
        ],
    ],
    'duplicate' => false,
];
    }
}
