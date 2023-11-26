<?php

class Test_Case_74 {
    public function test_case() {
        return [
    'test' => [
        'label' => 'test the service line created',
        'test_number' => 74,
        'aid' => 399,
        'sid' => 499,
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
                399,
            ],
        ],
    ],
    'expected' => [
        'billrun' => [
            'billrun_key' => '202103',
            'aid' => 399,
            'after_vat' => [
                499 => 117,
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
];
    }
}
