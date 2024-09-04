<?php

class Test_Case_8 {
    public function test_case() {
        return [
    'test' => [
        'test_number' => 8,
        'aid' => 13,
        'sid' => [
            15,
            16,
        ],
        'function' => [
            'basicCompare',
            'sumSids',
            'totalsPrice',
            'lineExists',
            'linesVSbillrun',
            'rounded',
        ],
        'options' => [
            'stamp' => '201806',
            'force_accounts' => [
                13,
            ],
        ],
    ],
    'expected' => [
        'billrun' => [
            'invoice_id' => 106,
            'billrun_key' => '201806',
            'aid' => 13,
            'after_vat' => [
                15 => 117,
                16 => 117,
            ],
            'total' => 234,
            'vatable' => 200,
            'vat' => 17,
        ],
        'line' => [
            'types' => [
                'flat',
            ],
        ],
    ],
    'postRun' => [
        'saveId',
    ],
];
    }
}
