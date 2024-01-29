<?php

class Test_Case_17 {
    public function test_case() {
        return [
    'test' => [
        'test_number' => 17,
        'aid' => 35,
        'sid' => [
            36,
            37,
            38,
            39,
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
            'stamp' => '201807',
            'force_accounts' => [
                35,
            ],
        ],
    ],
    'expected' => [
        'billrun' => [
            'invoice_id' => 115,
            'billrun_key' => '201807',
            'aid' => 35,
            'after_vat' => [
                36 => 117,
                37 => 117,
                38 => 117,
                39 => 117,
            ],
            'total' => 468,
            'vatable' => 400,
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
