<?php

class Test_Case_37 {
    public function test_case() {
        return [
    'test' => [
        'test_number' => 37,
        'aid' => 75,
        'sid' => 76,
        'function' => [
            'planExist',
            'basicCompare',
            'totalsPrice',
            'lineExists',
            'linesVSbillrun',
            'rounded',
        ],
        'options' => [
            'stamp' => '201808',
            'force_accounts' => [
                75,
            ],
        ],
    ],
    'expected' => [
        'billrun' => [
            'billrun_key' => '201808',
            'aid' => 75,
            'after_vat' => [
                76 => 117,
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
    'postRun' => [
        'saveId',
    ],
    'jiraLink' => [
        'https://billrun.atlassian.net/browse/BRCD-1725',
        'https://billrun.atlassian.net/browse/BRCD-1730',
    ],
];
    }
}
