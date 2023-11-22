<?php

class Test_Case_74 {
    public function test_case() {
        return [
    'test' => [
        'label' => 'test  subscriber with 2 discounts 1st about 3 month , from 02/12 to 02/03 2nd is about 26.32% forever , test thet the discount is created for 2 days in 03 ',
        'test_number' => 74,
        'aid' => 13262,
        'sid' => 82330,
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
                13262,
            ],
        ],
    ],
    'expected' => [
        'billrun' => [
            'billrun_key' => '202104',
            'aid' => 13262,
            'after_vat' => [
                82330 => 52.045827657463285,
            ],
            'total' => 52.045827657463285,
            'vatable' => 44.48361338244725,
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
