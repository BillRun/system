<?php

class Test_Case_76 {
    public function test_case() {
        return [
    'test' => [
        'test_number' => 76,
        'aid' => 145,
        'sid' => 245,
        'function' => [
            'checkForeignFileds',
            'basicCompare',
            'lineExists',
            'linesVSbillrun',
            'rounded',
        ],
        'checkForeignFileds' => [
            'plan' => [
                'foreign.plan.name' => 'PLAN_C',
            ],
            'service' => [
                'foreign.service.name' => 'NOT_TAXABLE',
            ],
            'discount' => [
                'foreign.service.name' => 'NOT_TAXABLE',
                'foreign.plan.name' => 'PLAN_C',
            ],
        ],
        'options' => [
            'stamp' => '202103',
            'force_accounts' => [
                145,
            ],
        ],
    ],
    'expected' => [
        'billrun' => [
            'invoice_id' => 108,
            'billrun_key' => '202103',
            'aid' => 145,
            'after_vat' => [
                245 => 207,
            ],
            'total' => 207,
            'vatable' => 190,
            'vat' => 17,
        ],
        'line' => [
            'types' => [
                'flat',
                'credit',
            ],
        ],
    ],
];
    }
}
