<?php

class Test_Case_3 {
    public function test_case() {
        return [
    'test' => [
        'test_number' => 3,
        'aid' => 3,
        'function' => [
            'basicCompare',
            'lineExists',
            'duplicateAccounts',
            'passthrough',
        ],
        'options' => [
            'stamp' => '201805',
            'force_accounts' => [
                3,
            ],
        ],
    ],
    'expected' => [
        'billrun' => [
            'invoice_id' => 101,
            'billrun_key' => '201805',
            'aid' => 3,
        ],
        'line' => [
            'types' => [
                'flat',
                'credit',
            ],
            'final_charge' => -10,
        ],
    ],
    'postRun' => [
    ],
];
    }
}
