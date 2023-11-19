<?php

class Test_Case_90 {
    public function test_case() {
        return [
    'test' => [
        'label' => 'discount for 3 cycles + discount unlimit from 15-02-2021,discount for 3 cyces  from 02-12-2020 to 02-03-2021, the service from is equal to revision from , 1st cycle  ',
        'test_number' => 90,
        'aid' => 991661,
        'sid' => 991662,
        'function' => [
            'basicCompare',
            'totalsPrice',
            'lineExists',
            'linesVSbillrun',
            'rounded',
        ],
        'options' => [
            'stamp' => '202101',
            'force_accounts' => [
                991661,
            ],
        ],
    ],
    'expected' => [
        'billrun' => [
            'billrun_key' => '202101',
            'aid' => 991661,
            'after_vat' => [
                991661 => 2.3829634288791293,
            ],
            'total' => 2.3829634288791293,
            'vatable' => 2.0367208793838714,
            'vat' => 17,
        ],
        'line' => [
            'types' => [
                'flat',
            ],
        ],
    ],
    'jiraLink' => '',
];
    }
}
