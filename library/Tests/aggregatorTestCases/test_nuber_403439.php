<?php

class Test_Case_403439 {
    public function test_case() {
        return [
    'test' => [
        'test_number' => 403439,
        'aid' => 823439,
        'sid' => 833439,
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
                823439,
            ],
        ],
    ],
    'expected' => [
        'billrun' => [
            'invoice_id' => 136,
            'billrun_key' => '201904',
            'aid' => 823439,
            'after_vat' => [
                833439 => 18.870967742,
            ],
        ],
    ],
    'line' => [
        'types' => [
            'flat',
            'service',
        ],
    ],
    'duplicate' => true,
];
    }
}
