<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of discountData
 *
 * @author yossi
 */
class discountData {

	public $Discount = [
		'DISCOUNT_EXAMPLE' => [
			'key' => 'DISCOUNT_EXAMPLE',
			'description' => 'New discount structure example',
			'from' => '2019-05-01T00:00:00Z',
			'to' => '2168-05-30T15:35:22Z',
			'creation_time' => '2019-05-01T00:00:00Z',
			'cycles' => 3,
			'type' => 'monetary',
			'params' =>
			[
				'min_subscribers' => 5,
				'max_subscribers' => 9,
				'conditions' =>
				[
					0 =>
					[
						'account' =>
						[
							'fields' =>
							[
								0 =>
								[
									'field_name' => 'street',
									'operator' => 'contains',
									'value' => 'odeon',
								],
								1 =>
								[
									'field_name' => 'category2',
									'operator' => 'does_not_equal',
									'value' => 'business1',
								],
							],
						],
						'subscriber' =>
						[
							0 =>
							[
								'fields' =>
								[
									0 =>
									[
										'field_name' => 'plan',
										'operator' => '$in',
										'value' =>
										[
											0 => 'PLAN_X',
										],
									],
									1 =>
									[
										'field_name' => 'former_plan',
										'operator' => '$nin',
										'value' =>
										[
											0 => 'PLAN_Y',
										],
									],
									2 =>
									[
										'field_name' => 'plan_activation',
										'operator' => '$gte',
										'value' => '2019-04-01T00:00:00+03:00',
									],
									3 =>
									[
										'field_name' => 'activation_date',
										'operator' => '$gte',
										'value' => '2019-04-01T00:00:00+03:00',
									],
									4 =>
									[
										'field_name' => 'plan_activation',
										'operator' => '$lte',
										'value' => '2019-05-01T00:00:00+03:00',
									],
									5 =>
									[
										'field_name' => 'contract.dates',
										'operator' => 'isActive',
										'value' => true,
									],
									6 =>
									[
										'field_name' => 'contract.type',
										'operator' => '$in',
										'value' =>
										[
											0 => 'Residential',
										],
									],
									7 =>
									[
										'field_name' => 'deactivation_date',
										'operator' => '$gt',
										'value' => '$$cycle_end_date',
									],
								],
								'service' =>
								[
									'any' =>
									[
										0 =>
										[
											'fields' =>
											[
												0 =>
												[
													'field_name' => 'name',
													'operator' => '$in',
													'value' =>
													[
														0 => 'CONTRACT1',
													],
												],
											],
										],
									],
								],
							],
						],
					],
				],
			],
			'limit' => 50,
			'subject' =>
			[
				'general' =>
				[
					'value' => 30,
				],
				'monthly_fees' =>
				[
					'value' => 30,
				],
				'matched_plans' =>
				[
					'value' => 30,
				],
				'matched_services' =>
				[
					'value' => 30,
				],
				'service' =>
				[
					'CONTRACT1' =>
					[
						'value' => 30,
					],
					'QQ' =>
					[
						'value' => 4,
						'operations' =>
						[
							0 =>
							[
								'name' => 'recurring_by_quantity',
								'params' =>
								[
									0 =>
									[
										'name' => 'quantity',
										'value' => 30,
									],
								],
							],
						],
					],
				],
				'plan' =>
				[
					'PLAN_X' => 30,
				],
			],
			'proration' => false,
			'excludes' =>
			[
				0 => 'DISCOUNT1',
				1 => 'DISCOUNT2',
			],
			'priority' => 3
		],
		'general' => [
			'key' => 'general',
			'description' => 'general',
			'from' => '2018-05-01T00:00:00Z',
			'to' => '2168-05-30T15:35:22Z',
			'creation_time' => '2019-08-01T00:00:00Z',
			'type' => 'monetary',
			'subject' => [],
			'proration' => true
		]
	];

	public $conditions = [
		'account' =>
		[
			'fields' =>
			[],
		],
		'subscriber' =>
		[
			[
				'fields' =>
				[],
				'service' =>
				[
					'any' =>
					[
						[
							'fields' =>
							[],
						],
					],
				],
			],
		],
	];

}
