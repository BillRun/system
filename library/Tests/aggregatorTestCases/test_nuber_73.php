<?php

class Test_Case_73 {
    public function test_case() {
        return [
    'test' => [
        'label' => 'test  subscriber with discount about 3 month , from 02/12 to 02/03 , test thet the discount is created for 2 days in 03 ',
        'test_number' => 73,
        'aid' => 13261,
        'sid' => 82329,
        'function' => [
            'basicCompare',
            'totalsPrice',
            'lineExists',
            'linesVSbillrun',
            'rounded',
        ],
        'options' => [
            'stamp' => '202104',
            'force_accounts' => [
                13261,
            ],
        ],
    ],
    'expected' => [
        'billrun' => [
            'billrun_key' => '202104',
            'aid' => 13261,
            'after_vat' => [
                82329 => 71.488902866374,
            ],
            'total' => 71.488902866374,
            'vatable' => 61.101626381516,
            'vat' => 17,
        ],
        'line' => [
            'types' => [
                'flat',
            ],
        ],
    ],
    'jiraLink' => 'https://billrun.atlassian.net/browse/BRCD-3133',
];
    }
}
