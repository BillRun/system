<?php

class Test_Case_177 {
    public function test_case() {
        return [
    'test' => [
        'test_number' => 177,
        'aid' => 882,
        'sid' => 772,
        'function' => [
            'basicCompare',
            'lineExists',
            'linesVSbillrun',
            'rounded',
        ],
        'options' => [
            'stamp' => '202108',
            'force_accounts' => [
                882,
            ],
        ],
    ],
    'expected' => [
        'billrun' => [
            'billrun_key' => '202108',
            'aid' => 882,
            'after_vat' => [
                772 => 58.5,
            ],
            'total' => 58.5,
            'vatable' => 50,
            'vat' => 17,
        ],
        'line' => [
            'types' => [
                'flat',
            ],
        ],
    ],
];
    }
}
