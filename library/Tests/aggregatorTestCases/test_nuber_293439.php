<?php

class Test_Case_293439 {
    public function test_case() {
        return [
    'test' => [
        'test_number' => 293439,
        'aid' => 623439,
        'sid' => 633439,
        'function' => [
            'basicCompare',
            'totalsPrice',
            'lineExists',
            'linesVSbillrun',
            'rounded',
        ],
        'options' => [
            'stamp' => '201808',
            'force_accounts' => [
                623439,
            ],
        ],
    ],
    'expected' => [
        'billrun' => [
            'invoice_id' => 126,
            'billrun_key' => '201808',
            'aid' => 623439,
            'after_vat' => [
                633439 => 117,
            ],
            'total' => 117,
            'vatable' => 100,
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
