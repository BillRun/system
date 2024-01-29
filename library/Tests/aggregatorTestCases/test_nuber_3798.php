<?php

class Test_Case_3798 {
    public function test_case() {
        return [
    'test' => [
        'test_number' => 3798,
        'aid' => 3798,
        'sid' => 37981,
        'function' => [
            'basicCompare',
            'lineExists',
            'linesVSbillrun',
            'rounded',
        ],
        'options' => [
            'stamp' => '202208',
            'force_accounts' => [
                3798,
            ],
        ],
    ],
    'expected' => [
        'billrun' => [
            'invoice_id' => 109,
            'billrun_key' => '202208',
            'aid' => 3798,
            'after_vat' => [
                3798 => 117.936,
            ],
            'total' => 117.936,
            'vatable' => 100.8,
            'vat' => 17,
        ],
    ],
];
    }
}
