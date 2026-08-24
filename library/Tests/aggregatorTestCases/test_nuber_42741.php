<?php

class Test_Case_42741 {
    public function test_case() {
        return [
    'test' => [
        'label' => 'BRCD-4614- check level price service  (3 levels) - full charge + period 2',
        'test_number' => 42741,
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
            'stamp' => '202403',
            'force_accounts' => [
                42740,
            ],
        ],
    ],
    'expected' => [
        'billrun' => [
            'billrun_key' => '202403',
            'aid' => 42740,
            'after_vat' => [
                42741 => 234,
            ],
            'total' => 234,
            'vatable' => 200,
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
