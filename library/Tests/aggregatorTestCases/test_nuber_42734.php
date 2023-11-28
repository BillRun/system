<?php

class Test_Case_42734 {
    public function test_case() {
        return [
    'test' => [
        'label' => 'DST',
        'test_number' => 42734,
        'aid' => 42734,
        'sid' => 42735,
        'function' => [
            'basicCompare',
            'sumSids',
            'totalsPrice',
            'lineExists',
            'linesVSbillrun',
            'rounded',
        ],
        'options' => [
            'stamp' => '202311',
            'force_accounts' => [
                42734,
            ],
        ],
    ],
    'expected' => [
        'billrun' => [
            'billrun_key' => '202311',
            'aid' => 42734,
            'after_vat' => [
                42735 => 0,
            ],
            'total' => 0,
            'vatable' => 0,
            'vat' => 17,
        ],
        'line' => [
            'types' => [
                'flat',
                'service',
            ],
        ],
    ],
];
    }
}
