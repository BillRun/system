<?php

class Test_Case_3439_74 {
    public function test_case() {
        return [
    'test' => [
        'label' => 'test the service line created',
        'test_number' => 743439,
        'aid' => 3993439,
        'sid' => 4993439,
        'function' => [
            'basicCompare',
            'totalsPrice',
            'lineExists',
            'linesVSbillrun',
            'rounded',
        ],
        'options' => [
            'stamp' => '202103',
            'force_accounts' => [
                3993439,
            ],
        ],
    ],
    'expected' => [
        'billrun' => [
            'billrun_key' => '202103',
            'aid' => 3993439,
            'after_vat' => [
                4993439 => 117,
            ],
            'total' => 117,
            'vatable' => 100,
            'vat' => 17,
        ],
        'line' => [
            'types' => [
                'flat',
            ],
        ],
    ],
    'jiraLink' => 'https://billrun.atlassian.net/browse/BRCD-3013',
    'duplicate' => true,
];
    }
}
