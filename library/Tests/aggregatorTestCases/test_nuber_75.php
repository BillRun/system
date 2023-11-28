<?php

class Test_Case_75 {
    public function test_case() {
        return [
    'test' => [
        'label' => 'test  subscriber with discount about 3 month , from 03/12 to 02/03 , test thet the discount is created for 3 days in 03 ',
        'test_number' => 75,
        'aid' => 13263,
        'sid' => 82331,
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
                13263,
            ],
        ],
    ],
    'expected' => [
        'billrun' => [
            'billrun_key' => '202104',
            'aid' => 13263,
            'after_vat' => [<?php

class Test_Case_75 {
    public function test_case() {
        return [
    'test' => [
        'label' => 'test the Conditional charge is applied only to one subscriber under the account instead of two',
        'test_number' => 75,
        'aid' => 3082,
        'sid' => [
            3083,
            3084,
        ],
        'function' => [
            'basicCompare',
            'sumSids',
            'totalsPrice',
            'lineExists',
            'linesVSbillrun',
            'rounded',
        ],
        'options' => [
            'stamp' => '202106',
            'force_accounts' => [
                3082,
            ],
        ],
    ],
    'expected' => [
        'billrun' => [
            'billrun_key' => '202106',
            'aid' => 3082,
            'after_vat' => [
                3083 => 175.5,
                3084 => 175.5,
            ],
            'total' => 351,
            'vatable' => 300,
            'vat' => 17,
        ],
        'line' => [
            'types' => [
                'credit',
            ],
        ],
    ],
];
    }
}
            ],
            'total' => 69.10593943749474,
            'vatable' => 59.064905502132255,
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
