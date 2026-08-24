<?php

class Test_Case_42740 {
    public function test_case() {
        return [
    'test' => [
        'label' => 'BRCD-4614- check level price service - full charge + period 1',
        'test_number' => 42740,
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
            'stamp' => '202401',
            'force_accounts' => [
                42740,
            ],
        ],
    ],
    'expected' => [
        'billrun' => [
            'billrun_key' => '202401',
            'aid' => 42740,
            'after_vat' => [
                42741 => 117,
            ],
            'total' => 117,
            'vatable' => 100,
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
