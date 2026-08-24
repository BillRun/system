<?php

class Test_Case_42744 {
    public function test_case() {
        return [
    'test' => [
        'label' => 'BRCD-4614- check level price service  porated end (4 days from 30 days) - period 3',
        'test_number' => 42744,
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
            'stamp' => '202407',
            'force_accounts' => [
                42740,
            ],
        ],
    ],
    'expected' => [
        'billrun' => [
            'billrun_key' => '202407',
            'aid' => 42740,
            'after_vat' => [
                42741 => 46.8,
            ],
            'total' => 46.8,
            'vatable' => 40,
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
