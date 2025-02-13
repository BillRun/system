<?php

class Test_Case_42742 {
    public function test_case() {
        return [
    'test' => [
        'label' => 'BRCD-4614- check level price service  (3 levels) - full charge + period 3',
        'test_number' => 42742,
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
            'stamp' => '202406',
            'force_accounts' => [
                42740,
            ],
        ],
    ],
    'expected' => [
        'billrun' => [
            'billrun_key' => '202406',
            'aid' => 42740,
            'after_vat' => [
                42741 => 351,
            ],
            'total' => 351,
            'vatable' => 300,
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
