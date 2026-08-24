<?php

class Test_Case_42743 {
    public function test_case() {
        return [
    'test' => [
        'label' => 'BRCD-4614- check level price service  (3 levels) - out of cycles number ',
        'test_number' => 42743,
        'aid' => 42740,
        'sid' => 42741,
        'function' => [
            'basicCompare',
            'sumSids',
            'totalsPrice',
            'lineExists',
            'linesVSbillrun',
            'rounded',
        ],
        'options' => [
            'stamp' => '202408',
            'force_accounts' => [
                42740,
            ],
        ],
    ],
    'expected' => [
        'billrun' => [
            'billrun_key' => '202408',
            'aid' => 42740,
            'after_vat' => [
                42741 => 0,
            ],
            'total' => 0,
            'vatable' => 0,
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
