<?php

class Test_Case_113439 {
    public function test_case() {
        return [
    'test' => [
        'test_number' => 113439,
        'aid' => 253439,
        'sid' => 263439,
        'function' => [
            'basicCompare',
            'totalsPrice',
            'lineExists',
            'linesVSbillrun',
            'rounded',
        ],
        'options' => [
            'stamp' => '201806',
            'force_accounts' => [
                253439,
            ],
        ],
    ],
    'expected' => [
        'billrun' => [
            'invoice_id' => 109,
            'billrun_key' => '201806',
            'aid' => 253439,
            'after_vat' => [
                263439 => 52.83870967741935,
            ],
            'total' => 52.83870967741935,
            'vatable' => 45.16129032258064,
            'vat' => 17,
        ],
        'line' => [
            'types' => [
                'flat',
            ],
        ],
    ],
    'duplicate' => true,
];
    }
}
