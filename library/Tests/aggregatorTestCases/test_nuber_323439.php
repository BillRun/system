<?php

class Test_Case_323439 {
    public function test_case() {
        return [
    'test' => [
        'test_number' => 323439,
        'aid' => 13439,
        'sid' => 23439,
        'function' => [
            'basicCompare',
            'totalsPrice',
            'lineExists',
            'linesVSbillrun',
            'rounded',
        ],
        'options' => [
            'stamp' => '201807',
            'force_accounts' => [
                13439,
            ],
        ],
    ],
    'expected' => [
        'billrun' => [
            'invoice_id' => 128,
            'billrun_key' => '201807',
            'aid' => 13439,
            'after_vat' => [
                23439 => 307,
            ],
            'total' => 307,
            'vatable' => 100,
            'vat' => 17,
        ],
        'line' => [
            'types' => [
                'flat',
                'non',
                'credit',
                'service',
            ],
        ],
    ],
    'duplicate' => true,
];
    }
}
