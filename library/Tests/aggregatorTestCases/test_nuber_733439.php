<?php

class Test_Case_733439 {
    public function test_case() {
        return [
    'test' => [
        'label' => 'test the prorated discounts days is rounded down',
        'test_number' => 733439,
        'aid' => 2303439,
        'sid' => 800183439,
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
                2303439,
            ],
        ],
    ],
    'expected' => [
        'billrun' => [
            'billrun_key' => '202012',
            'aid' => 2303439,
            'after_vat' => [
                800183439 => 35.778665181058486,
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
    'duplicate' => true,
];
    }
}
