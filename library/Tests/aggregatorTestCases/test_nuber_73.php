<?php

class Test_Case_73 {
    public function test_case() {
        return [
    'test' => [
        'label' => 'test the prorated discounts days is rounded down',
        'test_number' => 73,
        'aid' => 230,
        'sid' => 80018,
        'function' => [
            'basicCompare',
            'totalsPrice',
            'lineExists',
            'linesVSbillrun',
            'rounded',
        ],
        'options' => [
            'stamp' => '202012',
            'force_accounts' => [
                230,
            ],
        ],
    ],
    'expected' => [
        'billrun' => [
            'billrun_key' => '202012',
            'aid' => 230,
            'after_vat' => [
                80018 => 35.778665181058486,
            ],
            'total' => 35.778665181058486,
            'vatable' => 30.58005571030641,
            'vat' => 17,
        ],
        'line' => [
            'types' => [
                'flat',
            ],
        ],
    ],
    'jiraLink' => 'https://billrun.atlassian.net/browse/BRCD-3014',
];
    }
}
