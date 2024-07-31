<?php

class Test_Case_7 {
    public function test_case() {
        return [
    'test' => [
        'test_number' => 7,
        'aid' => 11,
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
                11,
            ],
        ],
    ],
    'expected' => [
        'billrun' => [
            'invoice_id' => 105,
            'billrun_key' => '201805',
            'aid' => 11,
            'after_vat' => [
                12 => 105.3,
                14 => 117,
            ],
        ],
    ],
];
    }
}
