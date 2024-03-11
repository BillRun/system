<?php

class Test_Case_57 {
    public function test_case() {
        return [
    'test' => [
        'test_number' => 57,
        'aid' => 1502,
        'sid' => 502,
        'function' => [
            'basicCompare',
            'totalsPrice',
            'lineExists',
            'linesVSbillrun',
            'rounded',
        ],
        'options' => [
            'stamp' => '202004',
            'force_accounts' => [
                1502,
            ],
        ],
    ],
    'expected' => [
        'billrun' => [
            'billrun_key' => '202004',
            'aid' => 1502,
            'after_vat' => [
                502 => 95.109677419355,
            ],
            'total' => 95.109677419355,
            'vatable' => 81.290322580645,
            'vat' => 17,
        ],
        'line' => [
            'types' => [
                'flat',
                'credit',
            ],
        ],
    ],
    'jiraLink' => 'https://billrun.atlassian.net/browse/BRCD-1913',
];
    }
}
