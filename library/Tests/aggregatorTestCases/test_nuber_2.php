<?php

class Test_Case_2 {
    public function test_case() {
        return [
    'test' => [
        'test_number' => 2,
        'aid' => 3,
        'function' => [
            'basicCompare',
            'checkInvoiceId',
            'invoice_exist',
            'lineExists',
            'passthrough',
        ],
        'invoice_path' => '201805_3_101.pdf',
        'options' => [
            'generate_pdf' => 1,
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
