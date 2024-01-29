<?php

class Test_Case_180 {
    public function test_case() {
        return [
    'test' => [
        'test_number' => 180,
        'aid' => 885,
        'sid' => 775,
        'function' => [
            'basicCompare',
            'lineExists',
            'linesVSbillrun',
            'rounded',
        ],
        'options' => [
            'stamp' => '202108',
            'force_accounts' => [
                885,
            ],
        ],
    ],
    'expected' => [
        'billrun' => [
            'billrun_key' => '202108',
            'aid' => 885,
            'after_vat' => [
                775 => 0,
            ],
            'total' => 0,
            'vatable' => 0,
            'vat' => 0,
        ],
        'line' => [
            'types' => [
                'flat',
                'credit',
            ],
        ],
    ],
];
    }
}
