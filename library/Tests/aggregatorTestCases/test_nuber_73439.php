<?php

class Test_Case_73439 {
    public function test_case() {
        return [
    'test' => [
        'test_number' => 73439,
        'aid' => 113439,
        'sid' => [
            12,
            14,
        ],
        'function' => [
            'basicCompare',
            'sumSids',
            'subsPrice',
            'linesVSbillrun',
            'rounded',
        ],
        'options' => [
            'stamp' => '201805',
            'force_accounts' => [
                113439,
            ],
        ],
    ],
    'expected' => [
        'billrun' => [
            'invoice_id' => 105,
            'billrun_key' => '201805',
            'aid' => 113439,
            'after_vat' => [
                123439 => 105.3,
                143439 => 117,
            ],
        ],
    ],
    'duplicate' => true,
];
    }
}
