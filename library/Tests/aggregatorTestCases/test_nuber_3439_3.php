<?php

class Test_Case_3439_3 {
    public function test_case() {
        return [
    'test' => [
        'test_number' => 33439,
        'aid' => 33439,
        'function' => [
            'basicCompare',
            'lineExists',
            'duplicateAccounts',
            'passthrough',
        ],
        'options' => [
            'stamp' => '201805',
            'force_accounts' => [
                33439,
            ],
        ],
    ],
    'expected' => [
        'billrun' => [
            'invoice_id' => 101,
            'billrun_key' => '201805',
            'aid' => 33439,
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
    'duplicate' => true,
];
    }
}
