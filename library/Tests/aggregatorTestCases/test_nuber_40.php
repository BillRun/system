<?php

class Test_Case_40 {
    public function test_case() {
        return [
    'test' => [
        'test_number' => 40,
        'aid' => 82,
        'sid' => 83,
        'function' => [
            'basicCompare',
            'subsPrice',
            'lineExists',
            'linesVSbillrun',
            'rounded',
        ],
        'options' => [
            'stamp' => '201904',
            'force_accounts' => [
                82,
            ],
        ],
    ],
    'expected' => [
        'billrun' => [
            'invoice_id' => 136,
            'billrun_key' => '201904',
            'aid' => 82,
            'after_vat' => [
                83 => 18.870967742,
            ],
        ],
    ],
    'line' => [
        'types' => [
            'flat',
            'service',
        ],
    ],
];
    }
}
