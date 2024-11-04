<?php

class Test_Case_20 {
    public function test_case() {
        return [
    'test' => [
        'test_number' => 20,
        'aid' => 88,
        'sid' => 89,
        'function' => [
            'billrunNotCreated',
        ],
        'options' => [
            'stamp' => '201806',
            'force_accounts' => [
                88,
            ],
        ],
    ],
    'expected' => [
        'billrun' => [
            'invoice_id' => 102,
            'billrun_key' => '201806',
            'aid' => 89,
        ],
        'line' => [
        ],
    ],
];
    }
}
