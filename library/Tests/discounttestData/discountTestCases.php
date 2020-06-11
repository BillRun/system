<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of discountTestCases
 *
 * @author yossi
 */
class discountTestCases {

	public $tests = [
		//min_max Account with 1 subscriber who is standing in the condition - eligibility for MaxSubscribers 
		array('test_num' => 1, 'test' => array('options' => ['stamp' => '201905'], 'subsAccount' => [['aid' => 1]], 'subsRevisions' => [
					[['sid' => 2, 'plan' => 'planMinMax', 'plan_activation' => '2019-01-01', 'from' => '2019-01-01', 'to' => '2119-07-02']]],
				'discounts' => [
					['name' => 'MinMaxSubscribers', 'root' => ['full_price' => 100],
						'params_override' => [
							'condition' => [[['type' => 'subscriber', 'field' => 'plan', 'values' => 'planMinMax']]],
							'min_subscribers' => 3,
							'max_subscribers' => 5
						]],
					['name' => 'MinSubscribers', 'root' => ['full_price' => 100],
						'params_override' => [
							'condition' => [[['type' => 'subscriber', 'field' => 'plan', 'values' => 'planMinMax']]],
							'min_subscribers' => 3
						]],
					['name' => 'MaxSubscribers', 'root' => ['full_price' => 100],
						'params_override' => [
							'condition' => [[['type' => 'subscriber', ['from' => '2019-01-01'], 'field' => 'plan', 'values' => 'planMinMax']]],
							'max_subscribers' => 5
						]]
				],
				'function' => array('checkEligibility')),
			'expected' => array("MaxSubscribers" => ["eligibility" => [["from" => "2019-04-01", "to" => "2019-05-01"]]])),
		//min_max Account with 3 subscribers who are standing in the condition - eligibility for  MaxSubscribers & MinSubscribers & MinMaxSubscribers
		array('test_num' => 2, 'test' => array('options' => ['stamp' => '201905'], 'subsAccount' => [['aid' => 3]], 'subsRevisions' => [
					[['sid' => 4, 'plan' => 'planMinMax', 'plan_activation' => '2019-01-01', 'from' => '2019-01-01', 'to' => '2119-07-02']],
					[['sid' => 5, 'plan' => 'planMinMax', 'plan_activation' => '2019-01-01', 'from' => '2019-01-01', 'to' => '2119-07-02']],
					[['sid' => 6, 'plan' => 'planMinMax', 'plan_activation' => '2019-01-01', 'from' => '2019-01-01', 'to' => '2119-07-02']]],
				'discounts' => [
					['name' => 'MinMaxSubscribers', 'root' => ['full_price' => 100],
						'params_override' => [
							'condition' => [[['type' => 'subscriber', 'field' => 'plan', 'values' => 'planMinMax']]],
							'min_subscribers' => 3,
							'max_subscribers' => 5
						]],
					['name' => 'MinSubscribers', 'root' => ['full_price' => 100],
						'params_override' => [
							'condition' => [[['type' => 'subscriber', 'field' => 'plan', 'values' => 'planMinMax']]],
							'min_subscribers' => 3
						]],
					['name' => 'MaxSubscribers', 'root' => ['full_price' => 100],
						'params_override' => [
							'condition' => [[['type' => 'subscriber', 'field' => 'plan', 'values' => 'planMinMax']]],
							'max_subscribers' => 5
						]]
				],
				'function' => array('checkEligibility')),
			'expected' => array(
				"MaxSubscribers" => ["eligibility" => [["from" => "2019-04-01", "to" => "2019-05-01"]]],
				"MinSubscribers" => ["eligibility" => [["from" => "2019-04-01", "to" => "2019-05-01"]]],
				"MinMaxSubscribers" => ["eligibility" => [["from" => "2019-04-01", "to" => "2019-05-01"]]]
			)
		),
		//min_max Account with 3 subscribers who aren’t standing in the condition - not eligibility for any discount
		array('test_num' => 3, 'test' => array('options' => ['stamp' => '201905'], 'subsAccount' => [['aid' => 7]], 'subsRevisions' => [
					[['sid' => 8, 'plan' => 'abc', 'from' => '2019-01-01', 'to' => '2119-07-02']],
					[['sid' => 9, 'plan' => 'abc', 'from' => '2019-01-01', 'to' => '2119-07-02']],
					[['sid' => 10, 'plan' => 'abc', 'from' => '2019-01-01', 'to' => '2119-07-02']]],
				'discounts' => [
					['name' => 'MinMaxSubscribers', 'root' => ['full_price' => 100],
						'params_override' => [
							'condition' => [[['type' => 'subscriber', 'field' => 'plan', 'values' => 'planMinMax']]],
							'min_subscribers' => 3,
							'max_subscribers' => 5
						]],
					['name' => 'MinSubscribers', 'root' => ['full_price' => 100],
						'params_override' => [
							'condition' => [[['type' => 'subscriber', 'field' => 'plan', 'values' => 'planMinMax']]],
							'min_subscribers' => 3
						]],
					['name' => 'MaxSubscribers', 'root' => ['full_price' => 100],
						'params_override' => [
							'condition' => [[['type' => 'subscriber', 'field' => 'plan', 'values' => 'planMinMax']]],
							'max_subscribers' => 5
						]]
				],
				'function' => array('checkEligibility')),
			'expected' => array()),
		//min_max Account with 6 subscribers who are standing in the condition - eligibility for MinSubscribers
		array('test_num' => 4, 'test' => array('options' => ['stamp' => '201905'], 'subsAccount' => [['aid' => 11]], 'subsRevisions' => [
					[['sid' => 8, 'plan' => 'planMinMax', 'from' => '2019-01-01', 'to' => '2119-07-02']],
					[['sid' => 9, 'plan' => 'planMinMax', 'from' => '2019-01-01', 'to' => '2119-07-02']],
					[['sid' => 10, 'plan' => 'planMinMax', 'from' => '2019-01-01', 'to' => '2119-07-02']],
					[['sid' => 11, 'plan' => 'planMinMax', 'from' => '2019-01-01', 'to' => '2119-07-02']],
					[['sid' => 12, 'plan' => 'planMinMax', 'from' => '2019-01-01', 'to' => '2119-07-02']],
					[['sid' => 13, 'plan' => 'planMinMax', 'from' => '2019-01-01', 'to' => '2119-07-02']]],
				'discounts' => [
					['name' => 'MinMaxSubscribers', 'root' => ['full_price' => 100],
						'params_override' => [
							'condition' => [[['type' => 'subscriber', 'field' => 'plan', 'values' => 'planMinMax']]],
							'min_subscribers' => 3,
							'max_subscribers' => 5
						]],
					['name' => 'MinSubscribers', 'root' => ['full_price' => 100],
						'params_override' => [
							'condition' => [[['type' => 'subscriber', 'field' => 'plan', 'values' => 'planMinMax']]],
							'min_subscribers' => 3
						]],
					['name' => 'MaxSubscribers', 'root' => ['full_price' => 100],
						'params_override' => [
							'condition' => [[['type' => 'subscriber', 'field' => 'plan', 'values' => 'planMinMax']]],
							'max_subscribers' => 5
						]]
				],
				'function' => array('checkEligibility')),
			'overideDiscount' => array([]),
			'expected' => array(
				"MinSubscribers" => ["eligibility" => [["from" => "2019-04-01", "to" => "2019-05-01"]]],
			)
		),
		/* min_max Account with (aid 18 ,sid 19,20,21 ):
		  From 01/04/2019
		  Sid 19 standing in the condition - sid 19 eligibility for  MaxSubscribers
		  From  05/04/2019
		  3  subscriber  standing in the condition - sid 19,20,21  eligibility for  MaxSubscribers & MinSubscribers & MinMaxSubscribers
		  From 10/04/2019
		  3  subscriber but only 19,21  standing in the condition -  eligibility for  MaxSubscribers */
		array('test_num' => 5, 'test' => array('options' => ['stamp' => '201905'], 'subsAccount' => [['aid' => 18]], 'subsRevisions' => [
					[['sid' => 19, 'plan' => 'planMinMax', 'from' => '2019-01-01', 'to' => '2019-05-01']],
					[
						['sid' => 20, 'plan' => 'abc', 'from' => '2019-04-01', 'to' => '2019-04-05'],
						['sid' => 20, 'plan' => 'planMinMax', 'from' => '2019-04-05', 'to' => '2019-04-10'],
						['sid' => 20, 'plan' => 'abc', 'from' => '2019-04-10', 'to' => '2019-05-01']
					],
					[
						['sid' => 21, 'plan' => 'abc', 'from' => '2019-01-01', 'to' => '2019-04-05'],
						['sid' => 21, 'plan' => 'planMinMax', 'from' => '2019-04-05', 'to' => '2019-05-01']
					],
				],
				'discounts' => [
					['name' => 'MinMaxSubscribers', 'root' => ['priority' => 1, 'subject' => ['matched_plans' => ['value' => 100]]],
						'params_override' => [
							'condition' => [[['type' => 'subscriber', 'field' => 'plan', 'values' => 'planMinMax']]],
							'min_subscribers' => 3,
							'max_subscribers' => 5
						]],
					['name' => 'MinSubscribers', 'root' => ['priority' => 2, 'subject' => ['matched_plans' => ['value' => 100]]],
						'params_override' => [
							'condition' => [[['type' => 'subscriber', 'field' => 'plan', 'values' => 'planMinMax']]],
							'min_subscribers' => 3
						]],
					['name' => 'MaxSubscribers', 'root' => ['priority' => 3, 'subject' => ['matched_plans' => ['value' => 100]]],
						'params_override' => [
							'condition' => [[['type' => 'subscriber', 'field' => 'plan', 'values' => 'planMinMax']]],
							'max_subscribers' => 5
						]]
				],
				'cdrs' => [
					['stamp' => 'a1', 'start' => '2019-04-01', 'end' => '2019-05-01', 'usaget' => 'flat', 'type' => 'flat', 'aid' => 18, 'sid' => 19, 'final_charge' => 117, 'full_price' => 100, 'billrun' => '201905', 'tax_data' => [], 'plan' => 'planMinMax'],
					['stamp' => 'a2', 'start' => '2019-04-01', 'end' => '2019-04-05', 'usaget' => 'flat', 'type' => 'flat', 'aid' => 18, 'sid' => 20, 'final_charge' => 19.49988, 'full_price' => 16.666666, 'billrun' => '201905', 'tax_data' => [], 'plan' => 'abc'],
					['stamp' => 'a3', 'start' => '2019-04-05', 'end' => '2019-04-10', 'usaget' => 'flat', 'type' => 'flat', 'aid' => 18, 'sid' => 20, 'final_charge' => 19.49988, 'full_price' => 16.666666, 'billrun' => '201905', 'tax_data' => [], 'plan' => 'planMinMax'],
					['stamp' => 'a4', 'start' => '2019-04-10', 'end' => '2019-05-01', 'usaget' => 'flat', 'type' => 'flat', 'aid' => 18, 'sid' => 20, 'final_charge' => 81.9, 'full_price' => 70, 'billrun' => '201905', 'tax_data' => [], 'plan' => 'abc'],
					['stamp' => 'a5', 'start' => '2019-04-01', 'end' => '2019-04-05', 'usaget' => 'flat', 'type' => 'flat', 'aid' => 18, 'sid' => 21, 'final_charge' => 19.49988, 'full_price' => 16.666666, 'billrun' => '201905', 'tax_data' => [], 'plan' => 'abc'],
					['stamp' => 'a6', 'start' => '2019-04-05', 'end' => '2019-05-01', 'usaget' => 'flat', 'type' => 'flat', 'aid' => 18, 'sid' => 21, 'final_charge' => 97.49999, 'full_price' => 83.33333, 'billrun' => '201905', 'tax_data' => [], 'plan' => 'planMinMax']
				],
				'function' => array('checkEligibility')),
			'expected' => array(
				"MaxSubscribers" => ["eligibility" => [["from" => "2019-04-01", "to" => "2019-05-01"]]],
				"MinSubscribers" => ["eligibility" => [["from" => "2019-04-05", "to" => "2019-04-10"]]],
				"MinMaxSubscribers" => ["eligibility" => [["from" => "2019-04-05", "to" => "2019-04-10"]]]
			),
			'subjectExpected' => [
				['sid' => 19, 'key' => 'MaxSubscribers', 'full_price' => -100, 'billrun' => '201905', 'final_charge' => -117, 'discount' => ['abc' => -100], 'affected_sections' => ['flat']],
				/* [ 'sid' => 19,'key' => 'MinSubscribers', 'full_price' => -16.66666, 'billrun' => '201905', 'final_charge'=> -19.4999, 'discount' => ['abc' => -3.3333], 'affected_sections' => ['flat']],
				  [ 'sid' => 19,'key' => 'MinMaxSubscribers', 'full_price' => -16.66666, 'billrun' => '201905', 'final_charge'=>-19.4999, 'discount' => ['abc' => -19.4999], 'affected_sections' => ['flat']], */
				['sid' => 20, 'key' => 'MaxSubscribers', 'full_price' => -16.66666, 'billrun' => '201905', 'final_charge' => -19.4999, 'discount' => ['abc' => -19.4999], 'affected_sections' => ['flat']],
				/* [ 'sid' => 20,'key' => 'MinSubscribers', 'full_price' => -16.66666, 'billrun' => '201905', 'final_charge'=>-19.4999, 'discount' => ['abc' => -19.4999], 'affected_sections' => ['flat']],
				  [ 'sid' => 20,'key' => 'MaxSubscribers', 'full_price' => -16.66666, 'billrun' => '201905', 'final_charge'=>-19.4999, 'discount' => ['abc' => -19.4999], 'affected_sections' => ['flat']],
				  [ 'sid' => 20,'key' => 'MinSubscribers', 'full_price' => -16.66666, 'billrun' => '201905', 'final_charge'=>-19.4999, 'discount' => ['abc' => -19.4999], 'affected_sections' => ['flat']], */
				['sid' => 21, 'key' => 'MaxSubscribers', 'full_price' => -83.333333, 'billrun' => '201905', 'final_charge' => -97.4999, 'discount' => ['abc' => -10], 'affected_sections' => ['flat']],
			/* [ 'sid' => 21,'key' => 'MinMaxSubscribers', 'full_price' => -16.66666, 'billrun' => '201905', 'final_charge'=>-19.4999, 'discount' => ['abc' => -19.4999], 'affected_sections' => ['flat']], */
			]
		),
		/*
		  condAccountA : street $eq abc
		  condAccountB : street $regex z
		  condAccountC : country $eq israel & street $ne z
		  Account with street abc :
		  expected result : the subscriber is eligible for discount  condAccountA
		 */
		array('test_num' => 6, 'test' => array('options' => ['stamp' => '201905'], 'subsAccount' => [['aid' => 18, 'street' => 'abc', 'from' => '2019-04-01', 'to' => '2019-05-01']], 'subsRevisions' => [
					[['sid' => 19, 'plan' => 'testAccountCondition', 'from' => '2019-01-01', 'to' => '2119-07-02']],],
				'discounts' => [
					['name' => 'condAccountA', 'root' => ['full_price' => 100],
						'params_override' => [
							'condition' => [[['type' => 'account', 'field' => 'street', 'values' => 'abc']]],
						]]],
				'function' => array('checkEligibility')),
			'expected' => array("condAccountA" => ["eligibility" => [["from" => "2019-04-01", "to" => "2019-05-01"]]])
		),
		/*
		  Account with street  abc & country israel :
		  expected result : the subscriber is eligible for discounts  condAccountC & condAccountA
		 */
		array('test_num' => 7, 'test' => array('options' => ['stamp' => '201905'], 'subsAccount' => [['aid' => 18, 'street' => 'abc', 'country' => 'israel']], 'subsRevisions' => [
					[['sid' => 19, 'plan' => 'planMinMax', 'from' => '2019-01-01', 'to' => '2119-07-02']],],
				'discounts' => [
					['name' => 'condAccountA', 'root' => ['full_price' => 100],
						'params_override' => [
							'condition' => [[['type' => 'account', 'field' => 'street', 'values' => 'abc']]],
						]],
					['name' => 'condAccountC', 'root' => ['full_price' => 100],
						'params_override' => [
							'condition' => [[['type' => 'account', 'field' => 'street', 'op' => 'ne', 'values' => 'z']]],
						]]],
				'function' => array('checkEligibility')),
			'expected' => array(
				"condAccountA" => ["eligibility" => [["from" => "2019-04-01", "to" => "2019-05-01"]]],
				"condAccountC" => ["eligibility" => [["from" => "2019-04-01", "to" => "2019-05-01"]]]
			)
		),
		/* Account with street  z & country israel eligible for discounts condAccountB */
		array('test_num' => 8, 'test' => array('options' => ['stamp' => '201905'], 'subsAccount' => [['aid' => 18, 'street' => 'z', 'country' => 'israel']], 'subsRevisions' => [
					[['sid' => 19, 'plan' => 'planMinMax', 'from' => '2019-01-01', 'to' => '2119-07-02']],],
				'discounts' => [
					['name' => 'condAccountB', 'root' => ['full_price' => 100],
						'params_override' => [
							'condition' => [[['type' => 'account', 'field' => 'street', 'op' => 'regex', 'values' => 'z']]],
						]],],
				'function' => array('checkEligibility')),
			'expected' => array(
				"condAccountB" => ["eligibility" => [["from" => "2019-04-01", "to" => "2019-05-01"]]]
			)
		),
		/* subscriber's discount tests */

		//Subscriber with SD with no condition and the subscriber is eligible for the discount
		array('test_num' => 9, 'test' => array('options' => ['stamp' => '201905'], 'subsAccount' => [['aid' => 3]],
				'subsRevisions' => [[['sid' => 25, 'plan' => 'abcdef', 'from' => '2019-01-01', 'to' => '2119-07-02']]],
				'function' => array('checkEligibility'),
				'discounts' => [
					['name' => 'condAccountB', 'root' => ['full_price' => 100],
						'params_override' => [
							'root' => ['full_price' => 100],
							'condition' => [[['type' => 'account', 'field' => 'street', 'op' => 'regex', 'values' => 'z']]],
						]],],
			),
			'overideDiscount' => array([]),
			'SubscribersDiscount' => array('25' => [
					'discounts' => [
						['name' => 'SDA', 'root' => ['full_price' => 100],
							'params_override' => [
								'condition' => [[['type' => '', 'field' => '', 'values' => '']]],
							]]
					]]
			),
			'expected' => array(
				"SDA" => ["eligibility" => [["from" => "2019-04-01", "to" => "2019-05-01"]]]
			)
		),
//		 Subscriber with SD with condition and the subscriber is eligible for the discount
		array('test_num' => 10, 'test' => array('options' => ['stamp' => '201905'], 'subsAccount' => [['aid' => 3]],
				'subsRevisions' => [[['sid' => 25, 'plan' => 'abc', 'from' => '2019-01-01', 'to' => '2119-07-02']]],
				'function' => array('checkEligibility'),
				'discounts' => [
					['name' => 'condAccountB', 'root' => ['full_price' => 100],
						'params_override' => [
							'condition' => [[['type' => 'account', 'field' => 'aid', /* 'op' => 'regex', */ 'values' => 4]]],
						]],],
			),
			'SubscribersDiscount' => array('25' => [
					'discounts' => [
						['name' => 'SDA', 'root' => ['full_price' => 100],
							'params_override' => [
								'condition' => [[['type' => 'subscriber', 'field' => 'plan', 'op' => 'eq', 'values' => 'abc']]],
							]]
					]]
			),
			'expected' => array(
				"SDA" => ["eligibility" => [["from" => "2019-04-01", "to" => "2019-05-01"]]]
			)
		),
		// Subscriber with SD with condition and the subscriber is not eligible for the discount
		array('test_num' => 11, 'test' => array('options' => ['stamp' => '201905'], 'subsAccount' => [['aid' => 3]],
				'subsRevisions' => [[['sid' => 25, 'plan' => 'abc', 'from' => '2019-01-01', 'to' => '2119-07-02']]],
				'function' => array('checkEligibility'),
				'discounts' => [
					['name' => 'condAccountB', 'root' => ['full_price' => 100],
						'params_override' => [
							'condition' => [[['type' => 'account', 'field' => 'street', 'op' => 'regex', 'values' => 'z']]],
						]],],
			),
			'overideDiscount' => array([]),
			'SubscribersDiscount' => array('25' => [
					'discounts' => [
						['name' => 'SDA', 'root' => ['full_price' => 100],
							'params_override' => [
								'condition' => [[['type' => 'subscriber', 'field' => 'plan', 'values' => 'abcd']]],
							]]
					]]
			),
			'expected' => array(
			)
		),
		//Subscriber with SD with condition and the subscriber is eligible for the discount  and more subscriber from same account who also eligibility for the discount
		array('test_num' => 12, 'test' => array('options' => ['stamp' => '201905'], 'subsAccount' => [['aid' => 3]],
				'subsRevisions' => [[['sid' => 25, 'plan' => 'abc', 'from' => '2019-01-01', 'to' => '2119-07-02']], [['sid' => 26, 'plan' => 'abc', 'from' => '2019-01-01', 'to' => '2119-07-02']]],
				'function' => array('checkEligibility'),
				'discounts' => [
					['name' => 'condAccountB', 'root' => ['full_price' => 100],
						'params_override' => [
							'condition' => [[['type' => 'account', 'field' => 'street', 'op' => 'regex', 'values' => 'z']]],
						]],],),
			'overideDiscount' => array([]),
			'SubscribersDiscount' => array('25' => [
					'discounts' => [
						['name' => 'SDA', 'root' => ['full_price' => 100],
							'params_override' => [
								'condition' => [[['type' => 'subscriber', 'field' => 'plan', 'values' => 'abc']]],
							]]
					]]
			),
			'expected' => array(
				"SDA" => ["eligibility" => [["from" => "2019-04-01", "to" => "2019-05-01"]]]
			)
		),
		// Subscriber with 2 SD with condition and the subscriber is eligible for the both +
		// eligible for more discount (condAccountB) but the SD is excludes the regular and condAccountB */
		array('test_num' => 13, 'test' => array('options' => ['stamp' => '201905'], 'subsAccount' => [['aid' => 3]],
				'subsRevisions' => [[['sid' => 25, 'plan' => 'abc', 'from' => '2019-01-01', 'to' => '2119-07-02']], [['sid' => 26, 'plan' => 'abc', 'from' => '2019-01-01', 'to' => '2119-07-02']]],
				'function' => array('checkEligibility'),
				'discounts' => [
					['name' => 'condAccountB', 'root' => ['priority' => 1],
						'params_override' => [
							'condition' => [[['type' => 'account', 'field' => 'street', 'op' => 'regex', 'values' => 'z']]],
						]],],
			),
			'overideDiscount' => array([]),
			'SubscribersDiscount' => array('25' => [
					'discounts' => [
						['name' => 'SDA', 'root' => ['priority' => 3, 'excludes' => ['regular', 'condAccountB']],
							'priority' => 3,
							'params_override' => [
								'condition' => [[['type' => 'subscriber', 'field' => 'plan', 'values' => 'abc']]],
							]],
						['name' => 'regular', 'root' => ['priority' => 2,],
							'params_override' => [
								'condition' => [[['type' => 'subscriber', 'field' => 'plan', 'values' => 'abc']]]
							]],
					]]
			),
			'expected' => array(
				"SDA" => ["eligibility" => [["from" => "2019-04-01", "to" => "2019-05-01"]]]
			)
		),
		/* coditions in subscriber */
		/*
		  plan_activation :from 01/03/2019
		  expected result : the subscriber isn’t eligible for the discount */
		array('test_num' => 14, 'test' => array('options' => ['stamp' => '201905'], 'subsAccount' => [['aid' => 18]], 'subsRevisions' => [
					[['sid' => 19, 'plan' => 'PLAN_X', 'from' => '2019-01-01', 'to' => '2019-05-01', "plan_activation" => "2019-02-01"]],
				],
				'discounts' => [
					['name' => 'conditionA',
						'params_override' => [
							'condition' => [[['type' => 'subscriber', 'field' => 'plan_activation', 'op' => '$gte', 'values' => '2019-03-01']]],
						]],
				],
				'function' => array('checkEligibility')),
			'expected' => array()
		),
		/* Subscriber is eligible for discount condSubscriberA for      full cycle */
		array('test_num' => 15, 'test' => array('options' => ['stamp' => '201905'], 'subsAccount' => [['aid' => 18]], 'subsRevisions' => [
					[['sid' => 19, 'activation_date' => '2020-04-01',
						'contract' => ['type' => 'Residential',
							'dates' => [["from" => "2019-04-01", "to" => "2019-05-01"]]],
						'former_plan' => 'PLAN_Z', 'plan' => 'PLAN_X', 'from' => '2019-04-01',
						'to' => '2019-05-01', 'deactivation_date' => "2019-08-01", "activation_date" => "2019-04-01", "plan_activation" => "2019-04-01 00:00:00"]],
				],
				'discounts' => [
					['name' => 'conditionA',
						'params_override' => [
							'condition' => [[
								['type' => 'subscriber', 'field' => 'contract.dates', 'op' => 'is', 'values' => 'active'],
								['type' => 'subscriber', 'field' => 'plan', 'op' => 'in', 'values' => 'PLAN_X'],
								['type' => 'subscriber', 'field' => 'former_plan', 'op' => 'nin', 'values' => 'PLAN_Y'],
								['type' => 'subscriber', 'field' => 'plan_activation', 'op' => 'gte', 'values' => '2019-04-01'],
								['type' => 'subscriber', 'field' => 'deactivation_date', 'op' => 'gt', 'values' => '@cycle_end_date@'],
								]],
						]],
				],
				'function' => array('checkEligibility')),
			'expected' => array(
				"conditionA" => ["eligibility" => [["from" => "2019-04-01", "to" => "2019-05-01"]]]
			)
		),
		/* expected result : Subscriber is eligible for discount condSubscriberA for     half cycle from 15 */
		array('test_num' => 16, 'test' => array('options' => ['stamp' => '201905'], 'subsAccount' => [['aid' => 18]], 'subsRevisions' => [
					[['sid' => 19, 'deactivation_date' => '2020-04-01', /* ["from" => "1555275600", "to" => "1556658000"] */
						'contract' => ['type' => 'Residential', 'dates' => [["from" => "2019-04-15", "to" => "2019-05-01"]]],
						'former_plan' => 'PLAN_Z', 'plan' => 'PLAN_X', 'from' => '2019-01-01',
						'to' => '2019-05-01', "activation_date" => "2019-04-01", "plan_activation" => "2019-04-01"]],
				],
				'discounts' => [
					['name' => 'conditionA',
						'params_override' => [
							'condition' => [[
								['type' => 'subscriber', 'field' => 'contract.dates', 'op' => '$is', 'values' => 'active'],
								['type' => 'subscriber', 'field' => 'plan', 'values' => 'PLAN_X'],
								['type' => 'subscriber', 'field' => 'former_plan', 'op' => '$nin', 'values' => 'PLAN_Y'],
								['type' => 'subscriber', 'field' => 'plan_activation', 'op' => '$gte', 'values' => '2019-04-01'],
								['type' => 'subscriber', 'field' => 'deactivation_date', 'op' => '$gt', 'values' => '@cycle_end_date@'],
								]],
						]],
				],
				'function' => array('checkEligibility')),
			'expected' => array(
				"conditionA" => ["eligibility" => [["from" => "2019-04-15", "to" => "2019-05-01"]]]
			)
		),
		array('test_num' => 17, 'test' => array('options' => ['stamp' => '201905'], 'subsAccount' => [['aid' => 18]], 'subsRevisions' => [
					[['sid' => 19, 'deactivation_date' => '2020-04-01',
						'contract' => ['type' => 'Residential', 'dates' => [["from" => "2019-03-15", "to" => "2019-04-15"]]],
						'former_plan' => 'PLAN_Z', 'plan' => 'PLAN_X', 'from' => '2019-01-01',
						'to' => '2119-07-02', "activation_date" => "2019-04-01", "plan_activation" => "2019-04-01"]],
				],
				'discounts' => [
					['name' => 'conditionA',
						'params_override' => [
							'condition' => [[
								['type' => 'subscriber', 'field' => 'contract.dates', 'op' => '$is', 'values' => 'active'],
								['type' => 'subscriber', 'field' => 'plan', 'op' => '$in', 'values' => 'PLAN_X'],
								['type' => 'subscriber', 'field' => 'former_plan', 'op' => '$nin', 'values' => 'PLAN_Y'],
								['type' => 'subscriber', 'field' => 'plan_activation', 'op' => '$gte', 'values' => '2019-04-01'],
								['type' => 'subscriber', 'field' => 'deactivation_date', 'op' => '$gt', 'values' => '@cycle_end_date@'],
								]],
						]],
				],
				'function' => array('checkEligibility')),
			'expected' => array(
				"conditionA" => ["eligibility" => [["from" => "2019-04-01", "to" => "2019-04-15"]]]
			)
		),
		array('test_num' => 18, 'test' => array('options' => ['stamp' => '201905'], 'subsAccount' => [['aid' => 18]], 'subsRevisions' => [
					[['sid' => 19, 'deactivation_date' => '2020-04-01',
						'contract' => ['type' => 'Residential', 'dates' => [["from" => "2019-04-10", "to" => "2019-04-21"]]],
						'former_plan' => 'PLAN_Z', 'plan' => 'PLAN_X', 'from' => '2019-01-01',
						'to' => '2119-07-02', "activation_date" => "2019-04-01", "plan_activation" => "2019-04-01"]],
				],
				'discounts' => [
					['name' => 'conditionA',
						'params_override' => [
							'condition' => [[
								['type' => 'subscriber', 'field' => 'contract.dates', 'op' => '$is', 'values' => 'active'],
								['type' => 'subscriber', 'field' => 'plan', 'op' => '$in', 'values' => 'PLAN_X'],
								['type' => 'subscriber', 'field' => 'former_plan', 'op' => '$nin', 'values' => 'PLAN_Y'],
								['type' => 'subscriber', 'field' => 'plan_activation', 'op' => '$gte', 'values' => '2019-04-01'],
								['type' => 'subscriber', 'field' => 'deactivation_date', 'op' => '$gt', 'values' => '@cycle_end_date@'],
								]],
						]],
				],
				'function' => array('checkEligibility')),
			'expected' => array(
				"conditionA" => ["eligibility" => [["from" => "2019-04-10", "to" => "2019-04-21"]]]
			)
		),
		array('test_num' => 19, 'test' => array('options' => ['stamp' => '201905'], 'subsAccount' => [['aid' => 18]], 'subsRevisions' => [
					[['sid' => 19, 'activation_date' => '2020-04-01',
						'contract' => ['type' => 'Residential', 'dates' => [["from" => "2019-03-10", "to" => "2019-03-10"]]],
						'former_plan' => 'PLAN_Z', 'plan' => 'PLAN_X', 'from' => '2019-01-01',
						'to' => '2119-07-02', "activation_date" => "2019-04-01", "plan_activation" => "2019-04-01"]],
				],
				'discounts' => [
					['name' => 'conditionA',
						'params_override' => [
							'condition' => [[
								['type' => 'subscriber', 'field' => 'contract.dates', 'op' => '$is', 'values' => 'active'],
								['type' => 'subscriber', 'field' => 'plan', 'op' => '$in', 'values' => 'PLAN_X'],
								['type' => 'subscriber', 'field' => 'former_plan', 'op' => '$nin', 'values' => 'PLAN_Y'],
								['type' => 'subscriber', 'field' => 'plan_activation', 'op' => '$gte', 'values' => '2019-04-01'],
								['type' => 'subscriber', 'field' => 'deactivation_date', 'op' => '$gt', 'values' => '@cycle_end_date@'],
								]],
						]],
				],
				'function' => array('checkEligibility')),
			'expected' => array(
			)
		),
		/* Subject : matched_plans 50 % ,type percentage
		  From   2019-04-01 to  2019-04-10 :00:00:00
		  contract.dates   : active
		  contractB.dates   :   active
		  From   2019-04-10 to  2019-04-20 :00:00
		  contract.dates   : notActive
		  contractB.dates   :   active
		  From   2019-04-20 to  2019-04-30 :00:00
		  contract.dates   : active
		  contractB.dates   :   active
		  expected result : Subscriber is eligible for:
		  From   2019-04-01 to  2019-04-10 :00:00
		  From   2019-04-20 to  2019-04-30 :00:00
		 */
		array('test_num' => 20, 'test' => array('options' => ['stamp' => '201905'], 'subsAccount' => [['aid' => 18]], 'subsRevisions' => [
					[['sid' => 19,
						'contract' => ['dates' => [["from" => "2019-04-01", "to" => "2019-04-11"], ["from" => "2019-04-20", "to" => "2019-05-01"]]],
						'contractB' => ['dates' => [["from" => "2019-04-01", "to" => "2019-05-01"]]],
						'plan' => 'PLAN_X', 'from' => '2019-04-01', 'to' => '2119-01-01',
						'plan_activation' => "2019-04-01"],],
				],
				'discounts' => [
					['name' => 'conditionB', 'root' => ['type' => 'percentage', 'subject' => ['matched_plans' => ['value' => 0.5]]],
						'params_override' => [
							'condition' => [[
								['type' => 'subscriber', 'field' => 'contractB.dates', 'op' => 'is', 'values' => 'active'],
								['type' => 'subscriber', 'field' => 'contract.dates', 'op' => 'is', 'values' => 'active'],
								['type' => 'subscriber', 'field' => 'plan', /* 'op' => 'eq' */ 'values' => 'PLAN_X'],
								]],
						]],
				],
				'cdrs' => [
					['prorated_end' => true, 'prorated_start' => true, 'usaget' => 'flat', 'type' => 'flat', 'start' => "2019-04-01", 'end' => '2019-05-01', 'aid' => 18, 'sid' => 19, 'final_charge' => 117, 'full_price' => 100, 'billrun' => '201905', 'tax_data' => [], 'plan' => 'PLAN_X'],
				],
				'function' => array('checkEligibility')),
			'expected' => array(
				"conditionB" => ["eligibility" => [["from" => "2019-04-01", "to" => "2019-04-11"], ["from" => "2019-04-20", "to" => "2019-05-01"]]]
			),
			'subjectExpected' => [
				['key' => 'conditionB', 'full_price' => -16.6666666, 'billrun' => '201905', 'final_charge' => -19.5, 'discount' => ['PLAN_X' => -10], 'plan' => 'PLAN_X', 'affected_sections' => ['plan']],
				['key' => 'conditionB', 'full_price' => -18.3333333, 'billrun' => '201905', 'final_charge' => -21.45, 'discount' => ['PLAN_X' => -10], 'plan' => 'PLAN_X', 'affected_sections' => ['plan']],
			]
		),
		/* test "Cycles" discounts  plans & services */
		/* Discounts :
		  D_SERVICE_FOR_3_MONTH:
		  Cycles : 3
		  Services :A,B,C
		  D_PLAN_FOR_3_MONTH
		  Cycles : 3
		  Plan  :A ,B
		  Plan  :
		  A,B
		  Services :
		  A,B,C
		 * 
		 * 
		 * sanity 
		  Run discount manager cycle for 201907
		  Subscriber with :
		  Plan_activition 2019-04-01
		  From 2019-04-01 Plan : A
		  expected result : Subscriber is eligible for discount D_PLAN_FOR_3_MONTH in billrun 201907

		 */
		array('test_num' => 21, 'test' => array('options' => ['stamp' => '201907'], 'subsAccount' => [['aid' => 18]], 'subsRevisions' => [
					[['sid' => 19, 'plan' => 'A', 'from' => '2019-04-01', 'to' => '2119-10-19', 'plan_activation' => '2019-04-01'],],
				],
				'discounts' => [
					['name' => 'D_PLAN_FOR_3_MONTH',
						'params_override' => [
							'condition' => [[]],
							'cycles' => 3,
						]],
				],
				'function' => array('checkEligibility')),
			'expected' => array(
				"D_PLAN_FOR_3_MONTH" => ["eligibility" => [["from" => "2019-06-01", "to" => "2019-07-01"]]],
			)
		),
		/*
		 * Discount for 3 months - run in the 4th  
		  expected result : Subscriber isn’t  eligible for discount D_PLAN_FOR_3_MONTH
		 */
		array('test_num' => 22, 'test' => array('options' => ['stamp' => '201908'], 'subsAccount' => [['aid' => 18]], 'subsRevisions' => [
					[['sid' => 19, 'plan' => 'A', 'from' => '2019-07-01', 'to' => '2119-04-01', 'plan_activation' => '2019-04-01'],],
				],
				'discounts' => [
					['name' => 'D_PLAN_FOR_3_MONTH',
						'params_override' => [
							'condition' => [[['type' => 'subscriber', 'field' => 'plan', 'values' => 'A']]],
							'cycles' => 3,
						]],
				],
				'function' => array('checkEligibility')),
			'expected' => array(
			)),
		/* Discount for 3 months - change plan in mid-cycle   
		  Run discount manager cycle for 201907
		  Subscriber with :
		  From 2019-04-01 Plan : A ,Plan_activition 2019-04-01
		  From 2019-06-15 Plan : B ,Plan_activition 2019-06-15
		  expected result : Subscriber is eligible for discount D_PLAN_FOR_3_MONTH
		  Form  2019-06-01
		  To      2019-06-15
		 */
		array('test_num' => 23, 'test' => array('options' => ['stamp' => '201907'], 'subsAccount' => [['aid' => 18]], 'subsRevisions' => [
					[
						['sid' => 19, 'plan' => 'A', 'from' => '2019-04-01', 'to' => '2019-06-15', 'plan_activation' => '2019-04-01'],
						['sid' => 19, 'plan' => 'B', 'from' => '2019-06-15', 'to' => '2119-08-15', 'plan_activation' => '2019-06-15']]
				],
				'discounts' => [
					['name' => 'D_PLAN_FOR_3_MONTH',
						'params_override' => [
							'condition' => [[['type' => 'subscriber', 'field' => 'plan', 'values' => 'A']]],
						/* 	'cycles' => 3, */
						]],
				],
				'function' => array('checkEligibility')),
			'expected' => array(
				"D_PLAN_FOR_3_MONTH" => ["eligibility" => [["from" => "2019-06-01", "to" => "2019-06-15"]]]
			)),
		/* sanity 
		  Run discount manager cycle for 201907
		  Subscriber with :
		  Service _activition 2019-04-01
		  From 2019-04-01 Service  : A
		  expected result : Subscriber is eligible for discount D_SERVICE_FOR_3_MONTH in billrun 201907

		 */
		array('test_num' => 24, 'test' => array('options' => ['stamp' => '201907'], 'subsAccount' => [['aid' => 18]], 'subsRevisions' => [
					[
						['sid' => 19, 'plan' => 'A', 'from' => '2019-04-01', 'to' => '2119-06-15', 'services' => [['name' => 'A', 'service_activation' => '2019-04-01', 'from' => '2019-04-01', 'to' => '2220-0101']], 'plan_activation' => '2019-04-01'],
					],
				],
				'discounts' => [
					['name' => 'D_SERVICE_FOR_3_MONTH',
						'params_override' => [
							'condition' => [[]],
							'cycles' => 3,
						]],
				],
				'function' => array('checkEligibility')),
			'expected' => array(
				"D_SERVICE_FOR_3_MONTH" => ["eligibility" => [["from" => "2019-06-01", "to" => "2019-07-01"]]]
			)),
		/* Discount for 3 months - run in the 4th  
		  Run discount manager cycle for 201908
		  Subscriber with :
		  Plan_activition 2019-04-01
		  From 2019-04-01 service : A
		  expected result : Subscriber isn’t  eligible for discount D_SERVICE_FOR_3_MONTH */
		array('test_num' => 25, 'test' => array('options' => ['stamp' => '201908'], 'subsAccount' => [['aid' => 18]], 'subsRevisions' => [
					[
						['sid' => 19, 'plan' => 'B', 'from' => '2019-07-01', 'to' => '2019-08-01',
							'services' => [['key' => 'A', 'name' => 'A', 'service_activation' => '2019-04-01', 'from' => '2019-04-01', 'to' => '2220-01-01']],
							'plan_activation' => '2019-04-01'],
					],
				],
				'discounts' => [
					['name' => 'D_SERVICE_FOR_3_MONTH',
						'params_override' => [
							'condition' => [[['type' => 'service', 'field' => 'name', 'values' => 'A']],],
							'cycles' => 3,
						]],
				],
				'function' => array('checkEligibility')),
			'expected' => array()),
//		/* Discount for 3 months - change service in mid-cycle   + matched_services
//		  Run discount manager cycle for 201907
//		  Subscriber with :
//		  From 2019-04-01 service : A ,service_activition 2019-04-01
//		  From 2019-06-15 service : B ,service_activition 2019-06-15
//		  expected result : Subscriber is eligible for discount D_SERVICE_FOR_3_MONTH
//		  Form  2019-06-01
//		  To      2019-06-15
//		 */
		array('test_num' => 26, 'test' => array('options' => ['stamp' => '201907'], 'subsAccount' => [['aid' => 18]], 'subsRevisions' => [
					[
						['sid' => 19, 'plan' => 'A', 'from' => '2019-06-01', 'to' => '2019-06-15', 'services' => [['name' => 'A', 'key' => 'A', 'service_activation' => '2019-04-01', 'from' => '2019-06-01', 'to' => '2019-06-15']], 'plan_activation' => '2019-04-01'],
						['sid' => 19, 'plan' => 'B', 'from' => '2019-06-01', 'to' => '2019-07-01', 'services' => [['name' => 'B', 'key' => 'B', 'service_activation' => '2019-04-01', 'from' => '2019-06-01', 'to' => '2019-07-01']], 'plan_activation' => '2019-04-01'],
					],
				],
				'discounts' => [
					['name' => 'D_SERVICE_FOR_3_MONTH', 'root' => ['subject' => ['matched_services' => ['value' => 100]]],
						'params_override' => [
							'condition' => [[
								['type' => 'service', 'field' => 'name', 'op' => '$eq', 'values' => 'A']],
							],
							'cycles' => 3,
						]],
				],
				'cdrs' => [
					['prorated_end' => true, 'prorated_start' => true, 'usaget' => 'flat', 'type' => 'service', 'start' => "2019-06-01", 'end' => '2019-06-15', 'aid' => 18, 'sid' => 19, 'final_charge' => 54.599988667, 'full_price' => 46.66666, 'billrun' => '201907', 'tax_data' => [], 'service' => 'A'],
				],
				'function' => array('checkEligibility')),
			'expected' => array(
				"D_SERVICE_FOR_3_MONTH" => ["eligibility" => [["from" => "2019-06-01", "to" => "2019-06-15"]]]
			),
			'subjectExpected' => [
				['key' => 'D_SERVICE_FOR_3_MONTH', 'full_price' => -46.66666, 'billrun' => '201907', 'final_charge' => -54.599988667, 'discount' => ['A' => -10], 'service' => 'A', 'affected_sections' => ['service']],
			]
		),
		/* Conflicting discounts + discounts priority */
		/* X :{
		  excludes :[ Z ]
		  condition : plan = abcd
		  & firstname =yossi
		  subject:{
		  monthly_fees:50
		  }
		  priority : 3
		  }
		  Z:{
		  excludes :[ Y]
		  condition : plan = abcd
		  subject:{
		  monthly_fees:50
		  }
		  priority : 2
		  }
		  Y:{
		  condition : plan = abcd
		  subject:{
		  monthly_fees:50
		  }
		  priority :1
		  }
		  AB:{
		  excludes :[ ABC ]
		  condition : plan = BB
		  subject:{
		  monthly_fees:50
		  }
		  priority : 2
		  }
		  ABC:{
		  condition : plan = BB
		  OR plan = ZZZ
		  subject:{
		  monthly_fees:50
		  }
		  priority :1
		  }
		 *
		 *  from 2019-04-01  eligibility for AB
		  sid :23 :{
		  plan :BB
		  }
		  from 2019-04-10 eligibility for ABC
		  sid :23 :{
		  plan :ZZZ
		  }
		  from 2019-04-20 eligibility for AB
		  sid :23 :{
		  plan :BB
		  }

		 */
		array('test_num' => 27, 'test' => array('options' => ['stamp' => '201905'], 'subsAccount' => [['aid' => 18]], 'subsRevisions' => [
					[
						['sid' => 19, 'plan' => 'BB', 'from' => '2019-04-01', 'to' => '2019-04-10', 'plan_activation' => '2019-04-01'],
						['sid' => 19, 'plan' => 'ZZZ', 'from' => '2019-04-10', 'to' => '2019-04-20', 'plan_activation' => '2019-04-10'],
						['sid' => 19, 'plan' => 'BB', 'from' => '2019-04-20', 'to' => '2019-05-01', 'plan_activation' => '2019-04-20']
					],
				],
				'discounts' => [
					['name' => 'AB', 'root' => ['subject' => ['monthly_fees' => 50], 'priority' => 2, 'excludes' => ['ABC']],
						'params_override' => [
							'condition' => [[
								['type' => 'subscriber', 'field' => 'plan', 'op' => '$eq', 'values' => 'BB'],
								]],
						]
					], ['name' => 'ABC', 'root' => ['subject' => ['monthly_fees' => 50], 'priority' => 1],
						'params_override' => [
							'condition' => [
								[['type' => 'subscriber', 'field' => 'plan', 'op' => '$eq', 'values' => 'BB']],
								[['type' => 'subscriber', 'field' => 'plan', 'op' => '$eq', 'values' => 'ZZZ']]
							],
						]
					]],
				'function' => array('checkEligibility')),
			'expected' => array(
				"AB" => ["eligibility" => [["from" => "2019-04-01", "to" => "2019-04-10"], ["from" => "2019-04-20", "to" => "2019-05-01"]]],
				"ABC" => ["eligibility" => [["from" => "2019-04-10", "to" => "2019-04-20"]]]
			)),
		/* from 2019-04-01  eligibility for X & Y
		  sid :22 :{
		  plan :abcd
		  firstname : yossi
		  }
		  from 2019-04-10 eligibility for Z
		  sid :22 :{
		  plan :abcd
		  firstname : yossef
		  }
		  from 2019-04-20 eligibility for X & Y
		  sid :22 :{
		  plan :abcd
		  firstname : yossi
		  }
		  // */
		array('test_num' => 28, 'test' => array('options' => ['stamp' => '201905'], 'subsAccount' => [['aid' => 18]], 'subsRevisions' => [
					[
						['sid' => 19, 'plan' => 'abcd', 'firstname' => 'yossi', 'from' => '2019-04-01', 'to' => '2019-04-10', 'plan_activation' => '2019-04-01'],
						['sid' => 19, 'plan' => 'abcd', 'firstname' => 'yossef', 'from' => '2019-04-10', 'to' => '2019-04-20', 'plan_activation' => '2019-04-10'],
						['sid' => 19, 'plan' => 'abcd', 'firstname' => 'yossi', 'from' => '2019-04-20', 'to' => '2019-05-01', 'plan_activation' => '2019-04-20']
					],
				],
				'discounts' => [
					['name' => 'X', 'root' => ['subject' => ['monthly_fees' => 50], 'priority' => 3, 'excludes' => ['Z']],
						'params_override' => [
							'condition' => [[
								['type' => 'subscriber', 'field' => 'plan', 'op' => '$eq', 'values' => 'abcd'],
								['type' => 'subscriber', 'field' => 'firstname', 'op' => '$eq', 'values' => 'yossi'],
								]],
						],
					],
					['name' => 'Z', 'root' => ['subject' => ['monthly_fees' => 50], 'priority' => 2, 'excludes' => ['Y']],
						'params_override' => [
							'condition' => [[
								['type' => 'subscriber', 'field' => 'plan', 'op' => '$eq', 'values' => 'abcd'],
								]]
						]
					], ['name' => 'Y', 'root' => ['subject' => ['monthly_fees' => 50], 'priority' => 1],
						'params_override' => [
							'condition' => [[
								['type' => 'subscriber', 'field' => 'plan', 'op' => '$eq', 'values' => 'abcd'],
								]],
						]
					]],
				'function' => array('checkEligibility')),
			'expected' => array(
				"X" => ["eligibility" => [["from" => "2019-04-01", "to" => "2019-04-10"], ["from" => "2019-04-20", "to" => "2019-05-01"]]],
				/* "Y" => ["eligibility" => [["from" => "2019-04-01", "to" => "2019-04-10"],["from" => "2019-04-10", "to" => "2019-05-01"]]], */
				"Z" => ["eligibility" => [["from" => "2019-04-10", "to" => "2019-04-20"]]]
			)),
		/* subject - monthly_fees */
		array('test_num' => 29, 'test' => array('options' => ['stamp' => '201905'], 'subsAccount' => [['aid' => 18]], 'subsRevisions' => [
					[['sid' => 19,
						'plan' => 'PLAN_X', 'from' => '2019-04-01', 'to' => '2019-05-01',
						'plan_activation' => "2019-04-01"],],
				],
				'discounts' => [
					['name' => 'abc', 'root' => ['subject' => ['monthly_fees' => ['value' => 100]]],
						'params_override' => [
							'condition' => [[
								['type' => 'subscriber', 'field' => 'plan', 'values' => 'PLAN_X'],
								]],
						]],
				],
				'cdrs' => [
					['usaget' => 'flat', 'type' => 'flat', 'start' => "2019-04-01", 'end' => '2019-05-01', 'aid' => 18, 'sid' => 19, 'final_charge' => 117, 'full_price' => 100, 'billrun' => '201905', 'tax_data' => [], 'plan' => 'PLAN_X'],
				],
				'function' => array('checkEligibility')),
			'expected' => array(
				"abc" => ["eligibility" => [["from" => "2019-04-01", "to" => "2019-05-01"]]]
			),
			'subjectExpected' => [
				['key' => 'abc', 'full_price' => -100, 'billrun' => '201905', 'final_charge' => -117, 'discount' => ['PLAN_X' => -100], 'plan' => 'PLAN_X', 'affected_sections' => ['plan']],
			]
		),
		/* mothly_fees - eligibility for partialy cycle */
		array('test_num' => 30, 'test' => array('options' => ['stamp' => '201905'], 'subsAccount' => [['aid' => 18]], 'subsRevisions' => [
					[['sid' => 19,
						'plan' => 'PLAN_X', 'from' => '2019-04-01', 'to' => '2019-04-15',
						'plan_activation' => "2019-04-01"],],
				],
				'discounts' => [
					['name' => 'abc', 'root' => ['subject' => ['monthly_fees' => ['value' => 100]]],
						'params_override' => [
							'condition' => [[
								['type' => 'subscriber', 'field' => 'plan', 'values' => 'PLAN_X'],
								]],
						]],
				],
				'cdrs' => [
					['usaget' => 'flat', 'type' => 'flat', 'start' => "2019-04-01", 'end' => '2019-04-15', 'aid' => 18, 'sid' => 19, 'final_charge' => 54.599998, 'full_price' => 46.66666, 'billrun' => '201905', 'tax_data' => [], 'plan' => 'PLAN_X'],
				],
				'function' => array('checkEligibility')),
			'expected' => array(
				"abc" => ["eligibility" => [["from" => "2019-04-01", "to" => "2019-04-15"],]]
			),
			'subjectExpected' => [
				['key' => 'abc', 'full_price' => -46.66666, 'billrun' => '201905', 'final_charge' => -54.599998, 'discount' => ['PLAN_X' => -46.66666], 'plan' => 'PLAN_X', 'affected_sections' => ['plan']],
			]
		),
		/* montly_fees - check the discount isn’t big from the subscriber monthly fees */
		array('test_num' => 31, 'test' => array('options' => ['stamp' => '201905'], 'subsAccount' => [['aid' => 18]], 'subsRevisions' => [
					[['sid' => 19,
						'plan' => 'PLAN_X', 'from' => '2019-04-01', 'to' => '2019-05-01',
						'plan_activation' => "2019-04-01"],],
				],
				'discounts' => [
					['name' => 'abc', 'root' => ['subject' => ['monthly_fees' => ['value' => 100]]],
						'params_override' => [
							'condition' => [[
								['type' => 'subscriber', 'field' => 'plan', 'values' => 'PLAN_X'],
								]],
						]],
				],
				'cdrs' => [
					['usaget' => 'flat', 'type' => 'flat', 'start' => "2019-04-01", 'end' => '2019-05-01', 'aid' => 18, 'sid' => 19, 'final_charge' => 58.5, 'full_price' => 50, 'billrun' => '201905', 'tax_data' => [], 'plan' => 'PLAN_X'],
				],
				'function' => array('checkEligibility')),
			'expected' => array(
				"abc" => ["eligibility" => [["from" => "2019-04-01", "to" => "2019-05-01"]]]
			),
			'subjectExpected' => [
				['key' => 'abc', 'full_price' => -50, 'billrun' => '201905', 'final_charge' => -58.5, 'discount' => ['PLAN_X' => -50], 'plan' => 'PLAN_X', 'affected_sections' => ['plan']],
			]
		),
		/* montly_fees - plan full_price 0 ,check the discount isn’t big from the subscriber monthly fees */
		array('test_num' => 32, 'test' => array('options' => ['stamp' => '201905'], 'subsAccount' => [['aid' => 18]], 'subsRevisions' => [
					[['sid' => 19,
						'plan' => 'PLAN_X', 'from' => '2019-04-01', 'to' => '2019-05-01',
						'plan_activation' => "2019-04-01"],],
				],
				'discounts' => [
					['name' => 'abfc', 'root' => ['subject' => ['monthly_fees' => ['value' => 100]]],
						'params_override' => [
							'condition' => [[
								['type' => 'subscriber', 'field' => 'plan', 'values' => 'PLAN_X'],
								]],
						]],
				],
				'cdrs' => [
					['usaget' => 'flat', 'type' => 'flat', 'start' => "2019-04-01", 'end' => '2019-05-01', 'aid' => 18, 'sid' => 19, 'final_charge' => 0, 'full_price' => 0, 'billrun' => '201905', 'tax_data' => [], 'plan' => 'PLAN_X'],
				],
				'function' => array()),
			'expected' => array(
				"abc" => ["eligibility" => [["from" => "2019-04-01", "to" => "2019-05-01"]]]
			),
			'subjectExpected' => [
			/* ['key' => 'abfc', 'full_price' => 0, 'billrun' => '201905', 'final_charge' => 0, 'discount' => ['PLAN_X' => 0], 'plan' => 'PLAN_X','affected_sections' => ['plan']], */
			]
		),
		/* montly_fees - plan full_price 0 service full_price 100 */
		array('test_num' => 33, 'test' => array('options' => ['stamp' => '201905'], 'subsAccount' => [['aid' => 18]], 'subsRevisions' => [
					[['sid' => 19,
						'plan' => 'PLAN_X', 'from' => '2019-04-01', 'to' => '2019-05-01',
						'plan_activation' => "2019-04-01"],
						'services' => [['key' => 'A', 'name' => 'A', 'service_activation' => '2019-04-01', 'from' => '2019-04-01', 'to' => '2019-05-01']],],
				],
				'discounts' => [
					['name' => 'abc', 'root' => ['subject' => ['monthly_fees' => ['value' => 100]]],
						'params_override' => [
							'condition' => [[
								['type' => 'subscriber', 'field' => 'plan', 'values' => 'PLAN_X'],
								]],
						]],
				],
				'cdrs' => [
					['usaget' => 'flat', 'type' => 'flat', 'start' => "2019-04-01", 'end' => '2019-05-01', 'aid' => 18, 'sid' => 19, 'final_charge' => 0, 'full_price' => 0, 'billrun' => '201905', 'tax_data' => [], 'plan' => 'PLAN_X'],
					['usaget' => 'flat', 'type' => 'service', 'start' => "2019-04-01", 'end' => '2019-05-01', 'aid' => 18, 'sid' => 19, 'final_charge' => 117, 'full_price' => 100, 'billrun' => '201905', 'tax_data' => [], 'service' => 'A']
				],
				'function' => array('checkEligibility')),
			'expected' => array(
				"abc" => ["eligibility" => [["from" => "2019-04-01", "to" => "2019-05-01"]]]
			),
			'subjectExpected' => [
				['key' => 'abc', 'full_price' => -100, 'billrun' => '201905', 'final_charge' => -117, 'discount' => ['A' => -50], 'service' => 'A', 'affected_sections' => ['service']],
			]
		),
		/* mutch plan */
		array('test_num' => 34, 'test' => array('options' => ['stamp' => '201905'], 'subsAccount' => [['aid' => 18]], 'subsRevisions' => [
					[['sid' => 19,
						'plan' => 'PLAN_X', 'from' => '2019-04-01', 'to' => '2019-05-01',
						'plan_activation' => "2019-04-01"],
						'services' => [['key' => 'A', 'name' => 'A', 'service_activation' => '2019-04-01', 'from' => '2019-04-01', 'to' => '2019-05-01']],],
				],
				'discounts' => [
					['name' => 'abc', 'root' => ['subject' => ['matched_plans' => ['value' => 100]]],
						'params_override' => [
							'condition' => [[
								['type' => 'subscriber', 'field' => 'plan', 'values' => 'PLAN_X'],
								]],
						]],
				],
				'cdrs' => [
					['prorated_end' => true, 'prorated_start' => true, 'usaget' => 'flat', 'type' => 'flat', 'start' => "2019-04-01", 'end' => '2019-05-01', 'aid' => 18, 'sid' => 19, 'final_charge' => 117, 'full_price' => 100, 'billrun' => '201905', 'tax_data' => [], 'plan' => 'PLAN_X'],
					['prorated_end' => true, 'prorated_start' => true, 'usaget' => 'flat', 'type' => 'service', 'start' => "2019-04-01", 'end' => '2019-05-01', 'aid' => 18, 'sid' => 19, 'final_charge' => 117, 'full_price' => 100, 'billrun' => '201905', 'tax_data' => [], 'service' => 'A']
				],
				'function' => array('checkEligibility')),
			'expected' => array(
				"abc" => ["eligibility" => [["from" => "2019-04-01", "to" => "2019-05-01"]]]
			),
			'subjectExpected' => [
				['key' => 'abc', 'full_price' => -100, 'billrun' => '201905', 'final_charge' => -117, 'discount' => ['PLAN_X' => -50], 'service' => 'PLAN_X', 'affected_sections' => ['plan']],
			]
		),
		/* subscriber with 2 revisions ,and has 2 discounts each discount is about one plan(mutch plan) */
		array('test_num' => 35, 'test' => array('options' => ['stamp' => '201905'], 'subsAccount' => [['aid' => 18]], 'subsRevisions' => [
					[['sid' => 19,
						'plan' => 'PLAN_X', 'from' => '2019-04-01', 'to' => '2019-04-15',
						'plan_activation' => "2019-04-01"],
						['sid' => 19,
							'plan' => 'PLAN_Y', 'from' => '2019-04-15', 'to' => '2019-05-01',
							'plan_activation' => "2019-04-15"]],
				],
				'discounts' => [
					['name' => 'abc', 'root' => ['subject' => ['matched_plans' => ['value' => 100]]],
						'params_override' => [
							'condition' => [[
								['type' => 'subscriber', 'field' => 'plan', 'values' => 'PLAN_X'],
								]],
						]],
					['name' => 'ab', 'root' => ['subject' => ['matched_plans' => ['value' => 50]]],
						'params_override' => [
							'condition' => [[
								['type' => 'subscriber', 'field' => 'plan', 'values' => 'PLAN_Y'],
								]],
						]],
				],
				'cdrs' => [
					['prorated_end' => true, 'prorated_start' => true, 'usaget' => 'flat', 'type' => 'flat', 'start' => "2019-04-01", 'end' => '2019-04-15', 'aid' => 18, 'sid' => 19, 'final_charge' => 54.599998, 'full_price' => 46.666666, 'billrun' => '201905', 'tax_data' => [], 'plan' => 'PLAN_X'],
					['prorated_end' => true, 'prorated_start' => true, 'usaget' => 'flat', 'type' => 'flat', 'start' => "2019-04-15", 'end' => '2019-05-01', 'aid' => 18, 'sid' => 19, 'final_charge' => 62.343333, 'full_price' => 53.333333, 'billrun' => '201905', 'tax_data' => [], 'plan' => 'PLAN_Y']
				],
				'function' => array()),
			'expected' => array(
			),
			'subjectExpected' => [
				['sid' => 19, 'key' => 'abc', 'full_price' => -46.66666, 'billrun' => '201905', 'final_charge' => -54.599998, 'discount' => ['PLAN_X' => -46.66666], 'service' => 'PLAN_X', 'affected_sections' => ['plan']],
				['sid' => 19, 'key' => 'ab', 'full_price' => -26.666, 'billrun' => '201905', 'final_charge' => -31.19998, 'discount' => ['PLAN_Y' => -26.666], 'service' => 'PLAN_Y', 'affected_sections' => ['plan']],
			]
		),
		/* subscriber with 3(X,Y,X) revisions ,and has 2 discounts each discount is about one plan(mutch plan) */
		array('test_num' => 36, 'test' => array('options' => ['stamp' => '201905'], 'subsAccount' => [['aid' => 18]], 'subsRevisions' => [
					[['sid' => 19,
						'plan' => 'PLAN_X', 'from' => '2019-04-01', 'to' => '2019-04-15',
						'plan_activation' => "2019-04-01"],
						['sid' => 19,
							'plan' => 'PLAN_Y', 'from' => '2019-04-15', 'to' => '2019-04-20',
							'plan_activation' => "2019-04-15"],
						['sid' => 19,
							'plan' => 'PLAN_X', 'from' => '2019-04-20', 'to' => '2019-05-01',
							'plan_activation' => "2019-04-20"]
					],
				],
				'discounts' => [
					['name' => 'abc', 'root' => ['subject' => ['matched_plans' => ['value' => 100]]],
						'params_override' => [
							'condition' => [[
								['type' => 'subscriber', 'field' => 'plan', 'values' => 'PLAN_X'],
								]],
						]],
					['name' => 'ab', 'root' => ['subject' => ['matched_plans' => ['value' => 100]]],
						'params_override' => [
							'condition' => [[
								['type' => 'subscriber', 'field' => 'plan', 'values' => 'PLAN_Y'],
								]],
						]],
				],
				'cdrs' => [
					['prorated_end' => true, 'prorated_start' => true, 'usaget' => 'flat', 'type' => 'flat', 'start' => "2019-04-01", 'end' => '2019-04-15', 'aid' => 18, 'sid' => 19, 'final_charge' => 54.599998, 'full_price' => 46.666666, 'billrun' => '201905', 'tax_data' => [], 'plan' => 'PLAN_X'],
					['prorated_end' => true, 'prorated_start' => true, 'usaget' => 'flat', 'type' => 'flat', 'start' => "2019-04-15", 'end' => '2019-04-20', 'aid' => 18, 'sid' => 19, 'final_charge' => 16.66666, 'full_price' => 19.499999, 'billrun' => '201905', 'tax_data' => [], 'plan' => 'PLAN_Y'],
					['prorated_end' => true, 'prorated_start' => true, 'usaget' => 'flat', 'type' => 'flat', 'start' => "2019-04-20", 'end' => '2019-05-01', 'aid' => 18, 'sid' => 19, 'final_charge' => 42.8998, 'full_price' => 36.6666, 'billrun' => '201905', 'tax_data' => [], 'plan' => 'PLAN_X']
				],
				'function' => array()),
			'expected' => array(
			),
			'subjectExpected' => [
				['sid' => 19, 'key' => 'abc', 'full_price' => -46.66666, 'billrun' => '201905', 'final_charge' => -54.599998, 'discount' => ['PLAN_X' => -46.66666], 'plan' => 'PLAN_X', 'affected_sections' => ['plan']],
				['sid' => 19, 'key' => 'ab', 'full_price' => -16.66666, 'billrun' => '201905', 'final_charge' => -19.499999, 'discount' => ['PLAN_Y' => -16.66666], 'plan' => 'PLAN_Y', 'affected_sections' => ['plan']],
				['sid' => 19, 'key' => 'abc', 'full_price' => -36.6666, 'billrun' => '201905', 'final_charge' => -42.89998, 'discount' => ['PLAN_X' => -36.6666], 'plan' => 'PLAN_X', 'affected_sections' => ['plan']],
			]
		),
		/* mutch services -service  + plan  mutchd_service A  */
		array('test_num' => 37, 'test' => array('options' => ['stamp' => '201905'], 'subsAccount' => [['aid' => 18]], 'subsRevisions' => [
					[['sid' => 19,
						'plan' => 'PLAN_Y', 'from' => '2019-04-01', 'to' => '2019-05-01',
						'plan_activation' => "2019-04-01",
						'services' => [['key' => 'A', 'name' => 'A', 'service_activation' => '2019-04-01', 'from' => '2019-04-01', 'to' => '2019-05-01']],
						]],
				],
				'discounts' => [
					['name' => 'ab', 'root' => ['subject' => ['matched_services' => ['value' => 100]]],
						'params_override' => [
							'condition' => [[
								['type' => 'service', 'field' => 'name', 'values' => 'A'],
								]],
						]],
				],
				'cdrs' => [
					['prorated_end' => true, 'prorated_start' => true, 'usaget' => 'flat', 'type' => 'flat', 'start' => "2019-04-01", 'end' => '2019-05-01', 'aid' => 18, 'sid' => 19, 'final_charge' => 117, 'full_price' => 100, 'billrun' => '201905', 'tax_data' => [], 'plan' => 'PLAN_Y'],
					['prorated_end' => true, 'prorated_start' => true, 'usaget' => 'flat', 'type' => 'service', 'start' => "2019-04-01", 'end' => '2019-05-01', 'aid' => 18, 'sid' => 19, 'final_charge' => 117, 'full_price' => 100, 'billrun' => '201905', 'tax_data' => [], 'service' => 'A'],
				],
				'function' => array()),
			'expected' => array(
			),
			'subjectExpected' => [
				['key' => 'ab', 'full_price' => -100, 'billrun' => '201905', 'final_charge' => -117, 'discount' => ['PLAN_X' => -100], 'service' => 'A', 'affected_sections' => ['service']],
			]
		),
		/* mutch services -service  + plan  mutchd_service A ,for partial cycle  */
		array('test_num' => 38, 'test' => array('options' => ['stamp' => '201905'], 'subsAccount' => [['aid' => 18]], 'subsRevisions' => [
					[['sid' => 19,
						'plan' => 'PLAN_X', 'from' => '2019-04-01', 'to' => '2019-05-01',
						'plan_activation' => "2019-04-01",
						'services' => [['key' => 'A', 'name' => 'A', 'service_activation' => '2019-04-01', 'from' => '2019-04-01', 'to' => '2019-04-15']],
						]],
				],
				'discounts' => [
					['name' => 'ab', 'root' => ['subject' => ['matched_services' => ['value' => 100]]],
						'params_override' => [
							'condition' => [[
								['type' => 'service', 'field' => 'name', 'values' => 'A'],
								]],
						]],
				],
				'cdrs' => [
					['usaget' => 'flat', 'type' => 'flat', 'start' => "2019-04-01", 'end' => '2019-05-01', 'aid' => 18, 'sid' => 19, 'final_charge' => 117, 'full_price' => 100, 'billrun' => '201905', 'tax_data' => [], 'plan' => 'PLAN_X'],
					['prorated_end' => true, 'prorated_start' => true, 'usaget' => 'flat', 'type' => 'service', 'start' => "2019-04-01", 'end' => '2019-04-15', 'aid' => 18, 'sid' => 19, 'final_charge' => 117, 'full_price' => 100, 'billrun' => '201905', 'tax_data' => [], 'service' => 'A'],
				],
				'function' => array()),
			'expected' => array(
			),
			'subjectExpected' => [
				['key' => 'ab', 'full_price' => -46.666, 'billrun' => '201905', 'final_charge' => -54.6, 'discount' => ['A' => -46.666], 'service' => 'A', 'affected_sections' => ['service']],
			]
		),
		/* subscriber with 3(X,Y,X) revisions ,and has 2 discounts each discount is about one plan(mutch service) */
		array('test_num' => 39, 'test' => array('options' => ['stamp' => '201905'], 'subsAccount' => [['aid' => 18]], 'subsRevisions' => [
					[['sid' => 19,
						'plan' => 'PLAN_X', 'from' => '2019-04-01', 'to' => '2019-04-15',
						'plan_activation' => "2019-04-01",
						'services' => [['key' => 'A', 'name' => 'A', 'service_activation' => '2019-04-01', 'from' => '2019-04-01', 'to' => '2019-04-15']],
						],
						['sid' => 19,
							'plan' => 'PLAN_Y', 'from' => '2019-04-15', 'to' => '2019-04-20',
							'plan_activation' => "2019-04-15",
							'services' => [['key' => 'B', 'name' => 'B', 'service_activation' => '2019-04-15', 'from' => '2019-04-15', 'to' => '2019-04-20']],
						],
						['sid' => 19,
							'plan' => 'PLAN_X', 'from' => '2019-04-20', 'to' => '2019-04-30',
							'plan_activation' => "2019-04-20",
							'services' => [['key' => 'A', 'name' => 'A', 'service_activation' => '2019-04-20', 'from' => '2019-04-20', 'to' => '2019-04-30']],
						]
					],
				],
				'discounts' => [
					['name' => 'abc', 'root' => ['subject' => ['matched_services' => ['value' => 100]]],
						'params_override' => [
							'condition' => [[
								['type' => 'service', 'field' => 'name', 'values' => 'A'],
								]],
						]],
					['name' => 'ab', 'root' => ['subject' => ['matched_services' => ['value' => 100]]],
						'params_override' => [
							'condition' => [[
								['type' => 'service', 'field' => 'name', 'values' => 'B'],
								]],
						]],
				],
				'cdrs' => [
					['prorated_end' => true, 'prorated_start' => true, 'usaget' => 'flat', 'type' => 'service', 'start' => "2019-04-01", 'end' => '2019-04-15', 'aid' => 18, 'sid' => 19, 'final_charge' => 54.599998, 'full_price' => 46.666666, 'billrun' => '201905', 'tax_data' => [], 'service' => 'A'],
					['prorated_end' => true, 'prorated_start' => true, 'usaget' => 'flat', 'type' => 'service', 'start' => "2019-04-15", 'end' => '2019-04-20', 'aid' => 18, 'sid' => 19, 'final_charge' => 16.66666, 'full_price' => 19.499999, 'billrun' => '201905', 'tax_data' => [], 'service' => 'B'],
					['prorated_end' => true, 'prorated_start' => true, 'usaget' => 'flat', 'type' => 'service', 'start' => "2019-04-20", 'end' => '2019-05-01', 'aid' => 18, 'sid' => 19, 'final_charge' => 33.3333, 'full_price' => 38.99999, 'billrun' => '201905', 'tax_data' => [], 'service' => 'A']
				],
				'function' => array()),
			'expected' => array(
			),
			'subjectExpected' => [
				['sid' => 19, 'key' => 'abc', 'full_price' => -46.66666, 'billrun' => '201905', 'final_charge' => -54.599998, 'discount' => ['A' => -46.66666], 'service' => 'A', 'affected_sections' => ['service']],
				['sid' => 19, 'key' => 'ab', 'full_price' => -16.66666, 'billrun' => '201905', 'final_charge' => -19.499999, 'discount' => ['B' => -16.66666], 'service' => 'B', 'affected_sections' => ['service']],
				['sid' => 19, 'key' => 'abc', 'full_price' => -33.33333, 'billrun' => '201905', 'final_charge' => -38.99999, 'discount' => ['A' => -33.33333], 'service' => 'A', 'affected_sections' => ['service']],
			]
		),
		/* service */
		array('test_num' => 40, 'test' => array('options' => ['stamp' => '201905'], 'subsAccount' => [['aid' => 18]], 'subsRevisions' => [
					[['sid' => 19, 'plan' => 'PLAN_X', 'from' => '2019-04-01', 'to' => '2019-05-01', 'plan_activation' => "2019-04-01",
						'services' => [['key' => 'A', 'name' => 'A', 'service_activation' => '2019-04-01', 'from' => '2019-04-01', 'to' => '2019-05-01']],
						],
					],
				],
				'discounts' => [
					['name' => 'ab', 'root' => ['subject' => ['service' => ['A' => ['value' => 100]]]],
						'params_override' => [
							'condition' => [[
								['type' => 'service', 'field' => 'name', 'values' => 'A'],
								]],
						]],
				],
				'cdrs' => [
					['usaget' => 'flat', 'type' => 'service', 'start' => "2019-04-01", 'end' => '2019-05-01', 'aid' => 18, 'sid' => 19, 'final_charge' => 100, 'full_price' => 117, 'billrun' => '201905', 'tax_data' => [], 'service' => 'A'],],
				'function' => array('checkEligibility')),
			'expected' => array(
				"ab" => ["eligibility" => [["from" => "2019-04-01", "to" => "2019-05-01"]]]
			),
			'subjectExpected' => [
				['sid' => 19, 'key' => 'ab', 'full_price' => -100, 'billrun' => '201905', 'final_charge' => -117, 'discount' => ['A' => -100], 'service' => 'A', 'affected_sections' => ['service']],
			]
		),
		array('test_num' => 41, 'test' => array('options' => ['stamp' => '201905'], 'subsAccount' => [['aid' => 18]], 'subsRevisions' => [
					[['sid' => 19, 'plan' => 'PLAN_X', 'from' => '2019-04-01', 'to' => '2019-05-01', 'plan_activation' => "2019-04-01",
						'services' => [['key' => 'A', 'quantity' => 10, 'name' => 'A', 'service_activation' => '2019-04-01', 'from' => '2019-04-01', 'to' => '2019-05-01']],
						]],
				],
				'discounts' => [
					['name' => 'ab', 'root' => ['subject' =>
							['service' =>
								["A" =>
									[
										"value" => 10,
										"operations" => [
											[
												"name" => "recurring_by_quantity",
												"params" => [
													[
														"name" => "quantity",
														"value" => 10
													]
												]
											]
										]
									]]]],
						'params_override' => [
							'condition' => [[
								['type' => 'service', 'field' => 'name', 'values' => 'A'],
								]],
						]],
				],
				'cdrs' => [
					['usaget' => 'flat', 'type' => 'service', 'quantity' => 10, 'start' => "2019-04-01", 'end' => '2019-05-01', 'aid' => 18, 'sid' => 19, 'final_charge' => 1000, 'price' => 1000, 'full_price' => 1117, 'billrun' => '201905', 'tax_data' => [], 'service' => 'A','usagev'=>100]
				],
				'function' => array('checkEligibility')),
			'expected' => array(
				"ab" => ["eligibility" => [["from" => "2019-04-01", "to" => "2019-05-01"]]]
			),
			'subjectExpected' => [
				['sid' => 19, 'key' => 'ab', 'full_price' => -100, 'billrun' => '201905', 'final_charge' => -117, 'discount' => ['A' => -100], 'service' => 'A', 'affected_sections' => ['service']],
			]
		),
		/* plan */
		array('test_num' => 42, 'test' => array('options' => ['stamp' => '201905'], 'subsAccount' => [['aid' => 18]], 'subsRevisions' => [
					[['sid' => 19, 'plan' => 'A', 'from' => '2019-04-01', 'to' => '2019-05-01', 'plan_activation' => "2019-04-01"],
					]],
				'discounts' => [
					['name' => 'abc', 'root' => ['subject' => ['plan' => ['A' => ['value' => 100]]]],
						'params_override' => [
							'condition' => [[
								['type' => 'subscriber', 'field' => 'plan', 'values' => 'A'],
								]],
						]],
				],
				'cdrs' => [
					['usaget' => 'flat', 'type' => 'flat', 'start' => "2019-04-01", 'end' => '2019-05-01', 'aid' => 18, 'sid' => 19, 'final_charge' => 100, 'full_price' => 117, 'billrun' => '201905', 'tax_data' => [], 'plan' => 'A'],],
				'function' => array()),
			'expected' => array(
			),
			'subjectExpected' => [
				['sid' => '19', 'key' => 'abc', 'full_price' => -100, 'billrun' => '201905', 'final_charge' => -117, 'discount' => ['A' => -100], 'plan' => 'A', 'affected_sections' => ['plan']],
			]
		),
		/* plan */
		array('test_num' => 43, 'test' => array('options' => ['stamp' => '201905'], 'subsAccount' => [['aid' => 18]], 'subsRevisions' => [
					[['sid' => 19, 'plan' => 'A', 'from' => '2019-04-01', 'to' => '2019-05-01', 'plan_activation' => "2019-04-01"],
					]],
				'discounts' => [
					['name' => 'abc', 'root' => ['subject' => ['plan' => ['A' => ['value' => 100]]]],
						'params_override' => [
							'condition' => [[
								['type' => 'subscriber', 'field' => 'plan', 'values' => 'A'],
								]],
						]],
				],
				'cdrs' => [
					['usaget' => 'flat', 'type' => 'flat', 'start' => "2019-04-01", 'end' => '2019-05-01', 'aid' => 18, 'sid' => 19, 'final_charge' => 100, 'full_price' => 117, 'billrun' => '201905', 'tax_data' => [], 'plan' => 'A'],],
				'function' => array()),
			'expected' => array(
			),
			'subjectExpected' => [
				['sid' => '19', 'key' => 'abc', 'full_price' => -100, 'billrun' => '201905', 'final_charge' => -117, 'discount' => ['A' => -100], 'plan' => 'A', 'affected_sections' => ['plan']],
			]
		),
		//2 subscribers with same plan and the subject is  the plan , only  sid 19 (yossi)is eligible for the discount 
		array('test_num' => 44, 'test' => array('options' => ['stamp' => '201905'], 'subsAccount' => [['aid' => 18]], 'subsRevisions' => [
					[['sid' => 19, 'plan' => 'A', 'from' => '2019-04-01', 'to' => '2019-05-01', 'plan_activation' => "2019-04-01",'firstname'=>'yossi']],
					[['sid' => 20, 'plan' => 'A', 'from' => '2019-04-01', 'to' => '2019-05-01', 'plan_activation' => "2019-04-01",'firstname'=>'yonatan']],
			],
				'discounts' => [
					['name' => 'abc', 'root' => ['subject' => ['plan' => ['A' => ['value' => 100]]]],
						'params_override' => [
							'condition' => [[
								['type' => 'subscriber', 'field' => 'firstname', 'op' => '$eq', 'values' => 'yossi'],
								]],
						]],
				],
				'cdrs' => [
					['usaget' => 'flat', 'type' => 'flat', 'start' => "2019-04-01", 'end' => '2019-05-01', 'aid' => 18, 'sid' => 19, 'final_charge' => 100, 'full_price' => 117, 'billrun' => '201905', 'tax_data' => [], 'plan' => 'A'],
					['usaget' => 'flat', 'type' => 'flat', 'start' => "2019-04-01", 'end' => '2019-05-01', 'aid' => 18, 'sid' => 20, 'final_charge' => 100, 'full_price' => 117, 'billrun' => '201905', 'tax_data' => [], 'plan' => 'A'],
					],
				'function' => array()),
			'expected' => array(
			),
			'subjectExpected' => [
				['sid' => '19', 'key' => 'abc', 'full_price' => -100, 'billrun' => '201905', 'final_charge' => -117, 'discount' => ['A' => -100], 'plan' => 'A', 'affected_sections' => ['plan']],
			]
		),
//		/*charge*/
//			array('test_num'=>45,'test' => array( 'charge_test'=>1,'options' => ['stamp' => '201905'], 'subsAccount' => [['aid' => 18]], 'subsRevisions' => [
//					[['sid' => 19, 'plan' => 'PLAN_X', 'from' => '2019-04-01','to' => '2019-05-01','plan_activation'=> "2019-04-01",
//						'services' => [['key' => 'A','name' => 'A', 'service_activation' => '2019-04-01', 'from' => '2019-04-01', 'to' => '2019-05-01']],
//					 ],
//					],
//				],
//				'discounts' => [
//				
//					['name' => 'ab','root'=>['subject'=>['general'=>['value'=>100]]],
//						'params_override' => [
//							'condition' => [[
//								['type' => 'service', 'field' => 'name', 'values' => 'A'],
//								]],
//						]],
//				],
//				'cdrs' => [
//					['usaget'=>'flat','type'=>'service','start'=>"2019-04-01",'end'=>'2019-05-01','aid' => 18, 'sid' => 19, 'final_charge' => 100 , 'full_price' => 117, 'billrun' => '201905', 'tax_data' => [], 'service' => 'A'],	],
//				),
//			'expected' => array(
//				"ab" => ["eligibility" => [["from" => "2019-04-01", "to" => "2019-05-01"]]]
//			),
//			'subjectExpected' => [
//				
//					['sid'=>19,'key' => 'ab', 'full_price' => 100, 'billrun' => '201905', 'final_charge' => 117 , 'discount' => ['A' => 100], 'service' => 'A','affected_sections' => ['service']],	
//				
//			]
//		),
		/*the discount is abount not vatable service - expected is the discount will be without vat*/
		array('test_num' => 46, 'test' => array('options' => ['stamp' => '201905'], 'subsAccount' => [['aid' => 77]], 'subsRevisions' => [
					[[  'sid' => 78,
						'plan' => 'PLAN_X', 'from' => '2019-04-01', 'to' => '2019-05-01',
						'plan_activation' => "2019-04-01",
						'services' => [['key' => 'A', 'name' => 'A', 'service_activation' => '2019-04-01', 'from' => '2019-04-01', 'to' => '2019-05-01']]]]],
				'discounts' => [
					['name' => 'abc', 'root' => ['subject' => ['service' => ['A' => ['value' => 10]]]],
						'params_override' => [
							'condition' => [[
								['type' => 'service', 'field' => 'name', 'values' => 'A'],
								]],
						]],
				],
				'cdrs' => [
					['usaget' => 'flat', 'type' => 'flat', 'start' => "2019-04-01", 'end' => '2019-05-01', 'aid' => 77, 'sid' => 78, 'final_charge' => 0, 'full_price' => 0, 'billrun' => '201905', 'tax_data' => [], 'plan' => 'PLAN_X'],
					['usaget' => 'flat', 'type' => 'service', 'start' => "2019-04-01", 'end' => '2019-05-01', 'aid' => 77, 'sid' => 78, 'final_charge' => 100, 'full_price' => 100, 'billrun' => '201905', 'tax_data' => [	"total_amount" => 0,"total_tax" => 0,"taxes" => [ ]], 'service' => 'A']
				],
				'function' => array('checkEligibility')),
			'expected' => array(
				"abc" => ["eligibility" => [["from" => "2019-04-01", "to" => "2019-05-01"]]]
			),
			'subjectExpected' => [
				['key' => 'abc', 'full_price' => -10, 'billrun' => '201905', 'final_charge' => -10, 'discount' => ['A' => -10], 'service' => 'A', 'affected_sections' => ['service']],
			]
		),
		/*the percentage discount is abount 100% of not vatable service - expected id the discount will be without vat and  anyway ton give more the service file charge*/
		array('test_num' => 47, 'test' => array('options' => ['stamp' => '201905'], 'subsAccount' => [['aid' => 77]], 'subsRevisions' => [
					[[  'sid' => 78,
						'plan' => 'PLAN_X', 'from' => '2019-04-01', 'to' => '2019-05-01',
						'plan_activation' => "2019-04-01",
						'services' => [['key' => 'A', 'name' => 'A', 'service_activation' => '2019-04-01', 'from' => '2019-04-01', 'to' => '2019-05-01']]]]],
				'discounts' => [
					['name' => 'abc', 'root' => ["type" => "percentage",'subject' => ['service' => ['A' => ['value' => 1]]]],
						'params_override' => [
							'condition' => [[
								['type' => 'service', 'field' => 'name', 'values' => 'A'],
								]],
						]],
				],
				'cdrs' => [
					['usaget' => 'flat', 'type' => 'flat', 'start' => "2019-04-01", 'end' => '2019-05-01', 'aid' => 77, 'sid' => 78, 'final_charge' => 0, 'full_price' => 0, 'billrun' => '201905', 'tax_data' => [], 'plan' => 'PLAN_X'],
					['usaget' => 'flat', 'type' => 'service', 'start' => "2019-04-01", 'end' => '2019-05-01', 'aid' => 77, 'sid' => 78, 'final_charge' => 100, 'full_price' => 100, 'billrun' => '201905', 'tax_data' => [	"total_amount" => 0,"total_tax" => 0,"taxes" => [ ]], 'service' => 'A']
				],
				'function' => array('checkEligibility')),
			'expected' => array(
				"abc" => ["eligibility" => [["from" => "2019-04-01", "to" => "2019-05-01"]]]
			),
			'subjectExpected' => [
				['key' => 'abc', 'full_price' => -100, 'billrun' => '201905', 'final_charge' => -100, 'discount' => ['A' => -100], 'service' => 'A', 'affected_sections' => ['service']],
			]
		),
		/* PLAN
		 * Subscriber is eligible to three sequential discounts for a full cycle*/
		array('test_num' => 48, 'test' => array('options' => ['stamp' => '201905'], 'subsAccount' => [['aid' => 21]], 'subsRevisions' => [
					[['sid' => 22, 'plan' => 'PLAN2', 'from' => '2019-04-01', 'to' => '2019-05-01', 'plan_activation' => "2019-04-01"],
					]],
				'discounts' => [
					['name' => 'DIS1', 'root' => ['type' => 'percentage', 'subject' => ['plan' => ['PLAN2' => ['value' => 0.25, 'sequential' => true]]]],
						'params_override' => [
							'condition' => [[
								['type' => 'subscriber', 'field' => 'plan', 'values' => 'PLAN2'],
								]],
						]],
					['name' => 'DIS2', 'root' => ['type' => 'percentage', 'subject' => ['plan' => ['PLAN2' => ['value' => 0.2, 'sequential' => true]]]],
						'params_override' => [
							'condition' => [[
								['type' => 'subscriber', 'field' => 'plan', 'values' => 'PLAN2'],
								]],
						]],
					['name' => 'DIS3', 'root' => ['type' => 'percentage', 'subject' => ['plan' => ['PLAN2' => ['value' => 0.5, 'sequential' => true]]]],
						'params_override' => [
							'condition' => [[
								['type' => 'subscriber', 'field' => 'plan', 'values' => 'PLAN2'],
								]],
						]],
				],
				'cdrs' => [
					['usaget' => 'flat', 'type' => 'flat', 'start' => "2019-04-01", 'end' => '2019-05-01', 'aid' => 21, 'sid' => 22, 'final_charge' => 117, 'full_price' => 100, 'billrun' => '201905', 'tax_data' => [], 'plan' => 'PLAN2']],
				'function' => array()),
			'expected' => array(
			),
			'subjectExpected' => [
				['sid' => '22', 'key' => 'DIS1', 'full_price' => -25, 'billrun' => '201905', 'final_charge' => -29.25,  'plan' => 'PLAN2', 'affected_sections' => ['plan']],
				['sid' => '22', 'key' => 'DIS2', 'full_price' => -15, 'billrun' => '201905', 'final_charge' => -17.55, 'plan' => 'PLAN2', 'affected_sections' => ['plan']],
				['sid' => '22', 'key' => 'DIS3', 'full_price' => -30, 'billrun' => '201905', 'final_charge' => -35.1, 'plan' => 'PLAN2', 'affected_sections' => ['plan']],
			]
		),
		/* PLAN
		 * Subscriber is eligible to three discounts:
 		 * DIS1 (sequential) from  17-19, DIS2(sequential) from 10-12, DIS3(sequential)from 1-20 
		 * (non conflicting percentage discounts, discount #1 applies first #2 secound)
		 * the last discount intercection with the DIS1 for 3 days and 
		 * with the DIS2 in others 3 days and the rest(14 days) separately
		 */
		array('test_num' => 49, 'test' => array('options' => ['stamp' => '201905'], 'subsAccount' => [['aid' => 21]], 'subsRevisions' => [
					[['sid' => 22, 'plan' => 'PLAN2', 'from' => '2019-04-01', 'to' => '2019-05-01', 'plan_activation' => "2019-04-01"],
					]],
				'discounts' => [
					['name' => 'DIS1', 'root' => ['from' => '2019-04-17', 'to' => '2019-04-20', 'type' => 'percentage', 'subject' => ['plan' => ['PLAN2' => ['value' => 0.1, 'sequential' => true]]]],
						'params_override' => [
							'condition' => [[
								['type' => 'subscriber', 'field' => 'plan', 'values' => 'PLAN2'],
								]],
						]],
					['name' => 'DIS2', 'root' => ['from' => '2019-04-10', 'to' => '2019-04-13', 'type' => 'percentage', 'subject' => ['plan' => ['PLAN2' => ['value' => 0.2, 'sequential' => true]]]],
						'params_override' => [
							'condition' => [[
								['type' => 'subscriber', 'field' => 'plan', 'values' => 'PLAN2'],
								]],
						]],
					['name' => 'DIS3', 'root' => ['from' => '2019-04-01', 'to' => '2019-04-21','type' => 'percentage', 'subject' => ['plan' => ['PLAN2' => ['value' => 0.3, 'sequential' => true]]]],
						'params_override' => [
							'condition' => [[
								['type' => 'subscriber', 'field' => 'plan', 'values' => 'PLAN2'],
								]],
						]],
				],
				'cdrs' => [
					['usaget' => 'flat', 'type' => 'flat', 'start' => "2019-04-01", 'end' => '2019-05-01', 'aid' => 21, 'sid' => 22, 'final_charge' => 351, 'full_price' => 300, 'billrun' => '201905', 'tax_data' => [], 'plan' => 'PLAN2']],
				'function' => array()),
			'expected' => array(
			),
			'subjectExpected' => [
				['sid' => '22', 'key' => 'DIS1', 'full_price' => -3, 'billrun' => '201905', 'final_charge' => -3.51, 'plan' => 'PLAN2', 'affected_sections' => ['plan']],
				['sid' => '22', 'key' => 'DIS2', 'full_price' => -6, 'billrun' => '201905', 'final_charge' => -7.02, 'plan' => 'PLAN2', 'affected_sections' => ['plan']],
				['sid' => '22', 'key' => 'DIS3', 'full_price' => -57.3, 'billrun' => '201905', 'final_charge' => -67.041, 'plan' => 'PLAN2', 'affected_sections' => ['plan']],
			]
		),
		/* PLAN
		 * Subscriber is eligible to three discounts:
 		 * #1 (sequential) from 12-29, #2(sequential) from 10-20, #3(sequential) from 1-15 
		 * (non conflicting percentage discounts, discount #1 applies first #2 secound)
		 * the last discount: intercection with DIS1 and DIS2 for 4 days and only with DIS2 for 2 days 
		 * and the rest(9 days) separately. 
		 * the second discount: intercection with DIS1 for 9 days and the rest(2 days) separately. 
		 */
		array('test_num' =>50, 'test' => array('options' => ['stamp' => '201905'], 'subsAccount' => [['aid' => 21]], 'subsRevisions' => [
					[['sid' => 22, 'plan' => 'PLAN2', 'from' => '2019-04-01', 'to' => '2019-05-01', 'plan_activation' => "2019-04-01"],
					]],
				'discounts' => [
					['name' => 'DIS1', 'root' => ['from' => '2019-04-12', 'to' => '2019-04-30', 'type' => 'percentage', 'subject' => ['plan' => ['PLAN2' => ['value' => 0.1, 'sequential' => true]]]],
						'params_override' => [
							'condition' => [[
								['type' => 'subscriber', 'field' => 'plan', 'values' => 'PLAN2'],
								]],
						]],
					['name' => 'DIS2', 'root' => ['from' => '2019-04-10', 'to' => '2019-04-21', 'type' => 'percentage', 'subject' => ['plan' => ['PLAN2' => ['value' => 0.2, 'sequential' => true]]]],
						'params_override' => [
							'condition' => [[
								['type' => 'subscriber', 'field' => 'plan', 'values' => 'PLAN2'],
								]],
						]],
					['name' => 'DIS3', 'root' => ['from' => '2019-04-01', 'to' => '2019-04-16','type' => 'percentage', 'subject' => ['plan' => ['PLAN2' => ['value' => 0.3, 'sequential' => true]]]],
						'params_override' => [
							'condition' => [[
								['type' => 'subscriber', 'field' => 'plan', 'values' => 'PLAN2'],
								]],
						]],
				],
				'cdrs' => [
					['usaget' => 'flat', 'type' => 'flat', 'start' => "2019-04-01", 'end' => '2019-05-01', 'aid' => 21, 'sid' => 22, 'final_charge' => 351, 'full_price' => 300, 'billrun' => '201905', 'tax_data' => [], 'plan' => 'PLAN2']],
				'function' => array()),
			'expected' => array(
			),
			'subjectExpected' => [
				['sid' => '22', 'key' => 'DIS1', 'full_price' => -18, 'billrun' => '201905', 'final_charge' => -21.06, 'plan' => 'PLAN2', 'affected_sections' => ['plan']],
				['sid' => '22', 'key' => 'DIS2', 'full_price' => -20.2, 'billrun' => '201905', 'final_charge' => -23.634, 'plan' => 'PLAN2', 'affected_sections' => ['plan']],
				['sid' => '22', 'key' => 'DIS3', 'full_price' => -40.494, 'billrun' => '201905', 'final_charge' => -47.37798, 'plan' => 'PLAN2', 'affected_sections' => ['plan']],
			]
		),
		/* PLAN
		 * BRCD-2588- EXAMPLE
		 * Subscriber is eligible to two discounts. #1 from 1-16, #2 (sequential) from 16-30 (non conflicting percentage discounts, discount #1 applies first)
		 * The 2nd discount should take into account the already discounted 16th of the month, 
		 * and the rest non-discounted 17-30.
		 */
		array('test_num' =>51, 'test' => array('options' => ['stamp' => '201905'], 'subsAccount' => [['aid' => 21]], 'subsRevisions' => [
					[['sid' => 22, 'plan' => 'PLAN2', 'from' => '2019-04-01', 'to' => '2019-05-01', 'plan_activation' => "2019-04-01"],
					]],
				'discounts' => [
					['name' => 'DIS1', 'root' => ['from' => '2019-04-01', 'to' => '2019-04-17', 'type' => 'percentage', 'subject' => ['plan' => ['PLAN2' => ['value' => 0.25]]]],
						'params_override' => [
							'condition' => [[
								['type' => 'subscriber', 'field' => 'plan', 'values' => 'PLAN2'],
								]],
						]],
					['name' => 'DIS2', 'root' => ['from' => '2019-04-16', 'to' => '2019-05-01', 'type' => 'percentage', 'subject' => ['plan' => ['PLAN2' => ['value' => 0.5, 'sequential' => true]]]],
						'params_override' => [
							'condition' => [[
								['type' => 'subscriber', 'field' => 'plan', 'values' => 'PLAN2'],
								]],
						]],
				],
				'cdrs' => [
					['prorated_end' => true, 'prorated_start' => true, 'usaget' => 'flat', 'type' => 'flat', 'start' => "2019-04-01", 'end' => '2019-05-01', 'aid' => 21, 'sid' => 22, 'final_charge' => 351, 'full_price' => 300, 'billrun' => '201905', 'tax_data' => [], 'plan' => 'PLAN2']],
				'function' => array()),
			'expected' => array(
			),
			'subjectExpected' => [
				['sid' => '22', 'key' => 'DIS1', 'full_price' => -40, 'billrun' => '201905', 'final_charge' => -46.8, 'plan' => 'PLAN2', 'affected_sections' => ['plan']],
				['sid' => '22', 'key' => 'DIS2', 'full_price' => -73.75, 'billrun' => '201905', 'final_charge' => -86.2875, 'plan' => 'PLAN2', 'affected_sections' => ['plan']],
			]
		),
		/* Service
		 * Subscriber is eligible to three sequential discounts for a full cycle*/
		array('test_num' => 52, 'test' => array('options' => ['stamp' => '201905'], 'subsAccount' => [['aid' => 21]], 'subsRevisions' => [
					[['sid' => 22, 'service' => 'SERVICE2', 'plan' => 'PLAN2', 'from' => '2019-04-01', 'to' => '2019-05-01',
						'plan_activation' => "2019-04-01",
						'services' => [['key' => 'SERVICE2', 'name' => 'SERVICE2', 'service_activation' => '2019-04-01', 'from' => '2019-04-01', 'to' => '2019-05-01']]]]],
				'discounts' => [
					['name' => 'DIS1', 'root' => ['type' => 'percentage', 'subject' => ['service' => ['SERVICE2' => ['value' => 0.25, 'sequential' => true]]]],
						'params_override' => [
							'condition' => [],
						]],
					['name' => 'DIS2', 'root' => ['type' => 'percentage', 'subject' => ['service' => ['SERVICE2' => ['value' => 0.2, 'sequential' => true]]]],
						'params_override' => [
							'condition' => [],
						]],
					['name' => 'DIS3', 'root' => ['type' => 'percentage', 'subject' => ['service' => ['SERVICE2' => ['value' => 0.5, 'sequential' => true]]]],
						'params_override' => [
							'condition' => [],
						]],
				],
				'cdrs' => [
					['usaget' => 'flat', 'type' => 'flat', 'start' => "2019-04-01", 'end' => '2019-05-01', 'aid' => 21, 'sid' => 22, 'final_charge' => 0, 'full_price' => 0, 'billrun' => '201905', 'tax_data' => [], 'plan' => 'PLAN2'],
					['usaget' => 'flat', 'type' => 'service', 'start' => "2019-04-01", 'end' => '2019-05-01', 'aid' => 21, 'sid' => 22, 'final_charge' => 117, 'full_price' => 100, 'billrun' => '201905', 'tax_data' => [	"total_amount" => 0,"total_tax" => 0,"taxes" => [ ]], 'service' => 'SERVICE2']
				],
				'function' => array()),
			'expected' => array(
			),
			'subjectExpected' => [
				['sid' => '22', 'key' => 'DIS1', 'full_price' => -25, 'billrun' => '201905',  'service' => 'SERVICE2', 'affected_sections' => ['service']],
				['sid' => '22', 'key' => 'DIS2', 'full_price' => -15, 'billrun' => '201905', 'service' => 'SERVICE2', 'affected_sections' => ['service']],
				['sid' => '22', 'key' => 'DIS3', 'full_price' => -30, 'billrun' => '201905', 'service' => 'SERVICE2','affected_sections' => ['service']],
			]
		),
		/* Service
		 * Subscriber is eligible to three discounts:
 		 * DIS1 (sequential) from  17-19, DIS2(sequential) from 10-12, DIS3(sequential)from 1-20 
		 * (non conflicting percentage discounts, discount #1 applies first #2 secound)
		 * the last discount intercection with the DIS1 for 3 days and 
		 * with the DIS2 in others 3 days and the rest(14 days) separately
		 */
		array('test_num' => 53, 'test' => array('options' => ['stamp' => '201905'], 'subsAccount' => [['aid' => 21]], 'subsRevisions' => [
					[['sid' => 22, 'service' => 'SERVICE2', 'plan' => 'PLAN2', 'from' => '2019-04-01', 'to' => '2019-05-01',
						'plan_activation' => "2019-04-01",
						'services' => [['key' => 'SERVICE2', 'name' => 'SERVICE2', 'service_activation' => '2019-04-01', 'from' => '2019-04-01', 'to' => '2019-05-01']]]]],
				'discounts' => [
					['name' => 'DIS1', 'root' => ['from' => '2019-04-17', 'to' => '2019-04-20', 'type' => 'percentage', 'subject' => ['service' => ['SERVICE2' => ['value' => 0.1, 'sequential' => true]]]],
						'params_override' => [
							'condition' => [],
						]],
					['name' => 'DIS2', 'root' => ['from' => '2019-04-10', 'to' => '2019-04-13', 'type' => 'percentage', 'subject' => ['service' => ['SERVICE2' => ['value' => 0.2, 'sequential' => true]]]],
						'params_override' => [
							'condition' => [],
						]],
					['name' => 'DIS3', 'root' => ['from' => '2019-04-01', 'to' => '2019-04-21','type' => 'percentage', 'subject' => ['service' => ['SERVICE2' => ['value' => 0.3, 'sequential' => true]]]],
						'params_override' => [
							'condition' => [],
						]],
				],
				'cdrs' => [
					['usaget' => 'flat', 'type' => 'flat', 'start' => "2019-04-01", 'end' => '2019-05-01', 'aid' => 21, 'sid' => 22, 'final_charge' => 0, 'full_price' => 0, 'billrun' => '201905', 'tax_data' => [], 'plan' => 'PLAN2'],
					['usaget' => 'flat', 'type' => 'service', 'start' => "2019-04-01", 'end' => '2019-05-01', 'aid' => 21, 'sid' => 22, 'final_charge' => 117, 'full_price' => 100, 'billrun' => '201905', 'tax_data' => [	"total_amount" => 0,"total_tax" => 0,"taxes" => [ ]], 'service' => 'SERVICE2']
				],
				'function' => array()),
			'expected' => array(
			),
			'subjectExpected' => [
				['sid' => '22', 'key' => 'DIS1', 'full_price' => -1, 'billrun' => '201905',  'service' => 'SERVICE2', 'affected_sections' => ['service']],
				['sid' => '22', 'key' => 'DIS2', 'full_price' => -2, 'billrun' => '201905', 'service' => 'SERVICE2', 'affected_sections' => ['service']],
				['sid' => '22', 'key' => 'DIS3', 'full_price' => -19.1, 'billrun' => '201905', 'service' => 'SERVICE2', 'affected_sections' => ['service']],
			]
		),
		/* Service
		 * Subscriber is eligible to three discounts:
 		 * #1 (sequential) from 12-29, #2(sequential) from 10-20, #3(sequential) from 1-15 
		 * (non conflicting percentage discounts, discount #1 applies first #2 secound)
		 * the last discount: intercection with DIS1 and DIS2 for 4 days and only with DIS2 for 2 days 
		 * and the rest(9 days) separately. 
		 * 
		 * + check condition by firstname
		 */
		array('test_num' => 54, 'test' => array('options' => ['stamp' => '201905'], 'subsAccount' => [['aid' => 21]], 'subsRevisions' => 
				[
					[['sid' => 22, 'plan' => 'PLAN2', 'from' => '2019-04-01', 'to' => '2019-05-01', 'plan_activation' => "2019-04-01",'firstname'=>'OR']],
					[['sid' => 23, 'plan' => 'PLAN2', 'from' => '2019-04-01', 'to' => '2019-05-01', 'plan_activation' => "2019-04-01",'firstname'=>'DANA']],
					[['sid' => 24, 'plan' => 'PLAN2', 'from' => '2019-04-01', 'to' => '2019-05-01', 'plan_activation' => "2019-04-01",'firstname'=>'NOA']],
				],
				'discounts' => [
					['name' => 'DIS1', 'root' => ['from' => '2019-04-12', 'to' => '2019-04-30', 'type' => 'percentage', 'subject' => ['service' => ['SERVICE2' => ['value' => 0.1, 'sequential' => true]]]],
						'params_override' => [
							'condition' => [[
								['type' => 'subscriber', 'field' => 'firstname', 'op' => '$eq', 'values' => 'DANA'],
								]],
						]],
					['name' => 'DIS2', 'root' => ['from' => '2019-04-10', 'to' => '2019-04-21', 'type' => 'percentage', 'subject' => ['service' => ['SERVICE2' => ['value' => 0.2, 'sequential' => true]]]],
						'params_override' => [
							'condition' => [[
								['type' => 'subscriber', 'field' => 'firstname', 'op' => '$eq', 'values' => 'DANA'],
								]],
						]],
					['name' => 'DIS3', 'root' => ['from' => '2019-04-01', 'to' => '2019-04-16','type' => 'percentage', 'subject' => ['service' => ['SERVICE2' => ['value' => 0.3, 'sequential' => true]]]],
						'params_override' => [
							'condition' => [[
								['type' => 'subscriber', 'field' => 'firstname', 'op' => '$eq', 'values' => 'DANA'],
								]],
						]],
				],
				'cdrs' => [
					['usaget' => 'flat', 'type' => 'flat', 'start' => "2019-04-01", 'end' => '2019-05-01', 'aid' => 21, 'sid' => 23, 'final_charge' => 0, 'full_price' => 0, 'billrun' => '201905', 'tax_data' => [], 'plan' => 'PLAN2'],
					['usaget' => 'flat', 'type' => 'service', 'start' => "2019-04-01", 'end' => '2019-05-01', 'aid' => 21, 'sid' => 23, 'final_charge' => 351, 'full_price' => 300, 'billrun' => '201905', 'tax_data' => [	"total_amount" => 0,"total_tax" => 0,"taxes" => [ ]], 'service' => 'SERVICE2'],
			['usaget' => 'flat', 'type' => 'flat', 'start' => "2019-04-01", 'end' => '2019-05-01', 'aid' => 21, 'sid' => 22, 'final_charge' => 0, 'full_price' => 0, 'billrun' => '201905', 'tax_data' => [], 'plan' => 'PLAN2'],
					['usaget' => 'flat', 'type' => 'service', 'start' => "2019-04-01", 'end' => '2019-05-01', 'aid' => 21, 'sid' => 22, 'final_charge' => 351, 'full_price' => 300, 'billrun' => '201905', 'tax_data' => [	"total_amount" => 0,"total_tax" => 0,"taxes" => [ ]], 'service' => 'SERVICE2']],
				'function' => array()),
			'expected' => array(
			),
			'subjectExpected' => [
				['sid' => '23', 'key' => 'DIS1', 'full_price' => -18, 'billrun' => '201905', 'service' => 'SERVICE2', 'affected_sections' => ['service']],
				['sid' => '23', 'key' => 'DIS2', 'full_price' => -20.2, 'billrun' => '201905', 'service' => 'SERVICE2', 'affected_sections' => ['service']],
				['sid' => '23', 'key' => 'DIS3', 'full_price' => -40.494, 'billrun' => '201905',  'service' => 'SERVICE2', 'affected_sections' => ['service']],
			]
		),
		/* Conflicting discounts + discounts priority + sequential*/
		/* X :{
		  excludes :[ Z ]
		  condition : plan = abcd
		  & firstname =yossi
		  subject:{
		  monthly_fees:50
		  }
		  priority : 3
		  }
		  Z:{
		  excludes :[ Y]
		  condition : plan = abcd
		  subject:{
		  monthly_fees:50
		  }
		  priority : 2
		  }
		  Y:{
		  condition : plan = abcd
		  subject:{
		  monthly_fees:50
		  }
		  priority :1
		  }
		  AB:{
		  excludes :[ ABC ]
		  condition : plan = BB
		  subject:{
		  monthly_fees:50
		  }
		  priority : 2
		  }
		  ABC:{
		  condition : plan = BB
		  OR plan = ZZZ
		  subject:{
		  monthly_fees:50
		  }
		  priority :1
		  }
		 *
		 *  from 2019-04-01  eligibility for AB
		  sid :23 :{
		  plan :BB
		  }
		  from 2019-04-10 eligibility for ABC
		  sid :23 :{
		  plan :ZZZ
		  }
		  from 2019-04-20 eligibility for AB
		  sid :23 :{
		  plan :BB
		  }

		 */
		array('test_num' => 55, 'test' => array('options' => ['stamp' => '201905'], 'subsAccount' => [['aid' => 18]], 'subsRevisions' => [
					[
						['sid' => 19, 'plan' => 'BB', 'from' => '2019-04-01', 'to' => '2019-04-10', 'plan_activation' => '2019-04-01'],
						['sid' => 19, 'plan' => 'ZZZ', 'from' => '2019-04-10', 'to' => '2019-04-20', 'plan_activation' => '2019-04-10'],
						['sid' => 19, 'plan' => 'BB', 'from' => '2019-04-20', 'to' => '2019-05-01', 'plan_activation' => '2019-04-20']
					],
				],
				'discounts' => [
					['name' => 'AB', 'root' => ['subject' => ['monthly_fees' => ['value' => 50, 'sequential' => true]], 'priority' => 2, 'excludes' => ['ABC']],
						'params_override' => [
							'condition' => [[
								['type' => 'subscriber', 'field' => 'plan', 'op' => '$eq', 'values' => 'BB'],
								]],
						]
					], ['name' => 'ABC', 'root' => ['subject' => ['monthly_fees' => ['value' => 50, 'sequential' => true]], 'priority' => 1],
						'params_override' => [
							'condition' => [
								[['type' => 'subscriber', 'field' => 'plan', 'op' => '$eq', 'values' => 'BB']],
								[['type' => 'subscriber', 'field' => 'plan', 'op' => '$eq', 'values' => 'ZZZ']]
							],
						]
					]],
				'function' => array('checkEligibility')),
			'expected' => array(
				"AB" => ["eligibility" => [["from" => "2019-04-01", "to" => "2019-04-10"], ["from" => "2019-04-20", "to" => "2019-05-01"]]],
				"ABC" => ["eligibility" => [["from" => "2019-04-10", "to" => "2019-04-20"]]]
			)),
		/* PLAN
		 * Subscriber is eligible to sequential discount but not a percentage discount (ignore the sequential)*/
		array('test_num' => 56, 'test' => array('options' => ['stamp' => '201905'], 'subsAccount' => [['aid' => 21]], 'subsRevisions' => [
					[['sid' => 22, 'plan' => 'PLAN2', 'from' => '2019-04-01', 'to' => '2019-05-01', 'plan_activation' => "2019-04-01"],
					]],
				'discounts' => [
					['name' => 'DIS1', 'root' => ['type' => 'monetary', 'subject' => ['plan' => ['PLAN2' => ['value' => 30, 'sequential' => true]]]],
						'params_override' => [
							'condition' => [[
								['type' => 'subscriber', 'field' => 'plan', 'values' => 'PLAN2'],
								]],
						]],
				],
				'cdrs' => [
					['usaget' => 'flat', 'type' => 'flat', 'start' => "2019-04-01", 'end' => '2019-05-01', 'aid' => 21, 'sid' => 22, 'final_charge' => 117, 'full_price' => 100, 'billrun' => '201905', 'tax_data' => [], 'plan' => 'PLAN2']],
				'function' => array()),
			'expected' => array(
			),
			'subjectExpected' => [
				['sid' => '22', 'key' => 'DIS1', 'full_price' => -30, 'billrun' => '201905', 'final_charge' => -35.1,  'plan' => 'PLAN2', 'affected_sections' => ['plan']],
			]
		),
		/* PLAN
		 * Subscriber is eligible to three sequential discounts for a full cycle
		 * the first is not a percentage discount (ignore the sequential for this discount)
		 */
		array('test_num' => 57, 'test' => array('options' => ['stamp' => '201905'], 'subsAccount' => [['aid' => 21]], 'subsRevisions' => [
					[['sid' => 22, 'plan' => 'PLAN2', 'from' => '2019-04-01', 'to' => '2019-05-01', 'plan_activation' => "2019-04-01"],
					]],
				'discounts' => [
					['name' => 'DIS1', 'root' => ['type' => 'monetary', 'subject' => ['plan' => ['PLAN2' => ['value' => 25, 'sequential' => true]]]],
						'params_override' => [
							'condition' => [[
								['type' => 'subscriber', 'field' => 'plan', 'values' => 'PLAN2'],
								]],
						]],
					['name' => 'DIS2', 'root' => ['type' => 'percentage', 'subject' => ['plan' => ['PLAN2' => ['value' => 0.2, 'sequential' => true]]]],
						'params_override' => [
							'condition' => [[
								['type' => 'subscriber', 'field' => 'plan', 'values' => 'PLAN2'],
								]],
						]],
					['name' => 'DIS3', 'root' => ['type' => 'percentage', 'subject' => ['plan' => ['PLAN2' => ['value' => 0.5, 'sequential' => true]]]],
						'params_override' => [
							'condition' => [[
								['type' => 'subscriber', 'field' => 'plan', 'values' => 'PLAN2'],
								]],
						]],
				],
				'cdrs' => [
					['usaget' => 'flat', 'type' => 'flat', 'start' => "2019-04-01", 'end' => '2019-05-01', 'aid' => 21, 'sid' => 22, 'final_charge' => 117, 'full_price' => 100, 'billrun' => '201905', 'tax_data' => [], 'plan' => 'PLAN2']],
				'function' => array()),
			'expected' => array(
			),
			'subjectExpected' => [
				['sid' => '22', 'key' => 'DIS1', 'full_price' => -25, 'billrun' => '201905', 'final_charge' => -29.25,  'plan' => 'PLAN2', 'affected_sections' => ['plan']],
				['sid' => '22', 'key' => 'DIS2', 'full_price' => -15, 'billrun' => '201905', 'final_charge' => -17.55, 'plan' => 'PLAN2', 'affected_sections' => ['plan']],
				['sid' => '22', 'key' => 'DIS3', 'full_price' => -30, 'billrun' => '201905', 'final_charge' => -35.1, 'plan' => 'PLAN2', 'affected_sections' => ['plan']],
			]
		),
		/* PLAN
		 * Subscriber is eligible to three sequential discounts for a full cycle
		 * the second is not a percentage discount (ignore the sequential for this discount)
		 */
		array('test_num' => 58, 'test' => array('options' => ['stamp' => '201905'], 'subsAccount' => [['aid' => 21]], 'subsRevisions' => [
					[['sid' => 22, 'plan' => 'PLAN2', 'from' => '2019-04-01', 'to' => '2019-05-01', 'plan_activation' => "2019-04-01"],
					]],
				'discounts' => [
					['name' => 'DIS3', 'root' => ['type' => 'percentage', 'subject' => ['plan' => ['PLAN2' => ['value' => 0.5, 'sequential' => true]]]],
						'params_override' => [
							'condition' => [[
								['type' => 'subscriber', 'field' => 'plan', 'values' => 'PLAN2'],
								]],
						]],
					['name' => 'DIS1', 'root' => ['type' => 'monetary', 'subject' => ['plan' => ['PLAN2' => ['value' => 25, 'sequential' => true]]]],
						'params_override' => [
							'condition' => [[
								['type' => 'subscriber', 'field' => 'plan', 'values' => 'PLAN2'],
								]],
						]],
					['name' => 'DIS2', 'root' => ['type' => 'percentage', 'subject' => ['plan' => ['PLAN2' => ['value' => 0.2, 'sequential' => true]]]],
						'params_override' => [
							'condition' => [[
								['type' => 'subscriber', 'field' => 'plan', 'values' => 'PLAN2'],
								]],
						]],
				],
				'cdrs' => [
					['usaget' => 'flat', 'type' => 'flat', 'start' => "2019-04-01", 'end' => '2019-05-01', 'aid' => 21, 'sid' => 22, 'final_charge' => 117, 'full_price' => 100, 'billrun' => '201905', 'tax_data' => [], 'plan' => 'PLAN2']],
				'function' => array()),
			'expected' => array(
			),
			'subjectExpected' => [
				['sid' => '22', 'key' => 'DIS1', 'full_price' => -25, 'billrun' => '201905', 'final_charge' => -29.25,  'plan' => 'PLAN2', 'affected_sections' => ['plan']],
				['sid' => '22', 'key' => 'DIS2', 'full_price' => -5, 'billrun' => '201905', 'final_charge' => -5.85, 'plan' => 'PLAN2', 'affected_sections' => ['plan']],
				['sid' => '22', 'key' => 'DIS3', 'full_price' => -50, 'billrun' => '201905', 'final_charge' => -58.5, 'plan' => 'PLAN2', 'affected_sections' => ['plan']],
			]
		),
		/* PLAN
		 * Subscriber is eligible to three sequential discounts for a full cycle
		 * the second is not a percentage discount (ignore the sequential for this discount)
		 * + more then 100%
		 */
		array('test_num' => 59, 'test' => array('options' => ['stamp' => '201905'], 'subsAccount' => [['aid' => 21]], 'subsRevisions' => [
					[['sid' => 22, 'plan' => 'PLAN2', 'from' => '2019-04-01', 'to' => '2019-05-01', 'plan_activation' => "2019-04-01"],
					]],
				'discounts' => [
					['name' => 'DIS3', 'root' => ['type' => 'percentage', 'subject' => ['plan' => ['PLAN2' => ['value' => 0.5, 'sequential' => true]]]],
						'params_override' => [
							'condition' => [[
								['type' => 'subscriber', 'field' => 'plan', 'values' => 'PLAN2'],
								]],
						]],
					['name' => 'DIS1', 'root' => ['type' => 'monetary', 'subject' => ['plan' => ['PLAN2' => ['value' => 50, 'sequential' => true]]]],
						'params_override' => [
							'condition' => [[
								['type' => 'subscriber', 'field' => 'plan', 'values' => 'PLAN2'],
								]],
						]],
					['name' => 'DIS2', 'root' => ['type' => 'percentage', 'subject' => ['plan' => ['PLAN2' => ['value' => 0.2, 'sequential' => true]]]],
						'params_override' => [
							'condition' => [[
								['type' => 'subscriber', 'field' => 'plan', 'values' => 'PLAN2'],
								]],
						]],
				],
				'cdrs' => [
					['usaget' => 'flat', 'type' => 'flat', 'start' => "2019-04-01", 'end' => '2019-05-01', 'aid' => 21, 'sid' => 22, 'final_charge' => 117, 'full_price' => 100, 'billrun' => '201905', 'tax_data' => [], 'plan' => 'PLAN2']],
				'function' => array()),
			'expected' => array(
			),
			'subjectExpected' => [
				['sid' => '22', 'key' => 'DIS1', 'full_price' => -50, 'billrun' => '201905', 'final_charge' => -58.5,  'plan' => 'PLAN2', 'affected_sections' => ['plan']],
				['sid' => '22', 'key' => 'DIS3', 'full_price' => -50, 'billrun' => '201905', 'final_charge' => -58.5, 'plan' => 'PLAN2', 'affected_sections' => ['plan']],
			]
		),
		/* Service
		 * three  Subscribers  eligible to one discount:
 		 * DIS1 (sequential) from  12-29
		 */
		array('test_num' => 60, 'test' => array('options' => ['stamp' => '201905'], 'subsAccount' => [['aid' => 21]], 'subsRevisions' => 
				[
					[['sid' => 22, 'plan' => 'PLAN2', 'from' => '2019-04-01', 'to' => '2019-05-01', 'plan_activation' => "2019-04-01"]],
					[['sid' => 23, 'plan' => 'PLAN2', 'from' => '2019-04-01', 'to' => '2019-05-01', 'plan_activation' => "2019-04-01"]],
					[['sid' => 24, 'plan' => 'PLAN2', 'from' => '2019-04-01', 'to' => '2019-05-01', 'plan_activation' => "2019-04-01"]],
				],
				'discounts' => [
					['name' => 'DIS1', 'root' => ['from' => '2019-04-12', 'to' => '2019-04-30', 'type' => 'percentage', 'subject' => ['service' => ['SERVICE2' => ['value' => 0.1, 'sequential' => true]]]],
						'params_override' => [
							'condition' => [],
						]],
				],
				'cdrs' => [
					['usaget' => 'flat', 'type' => 'flat', 'start' => "2019-04-01", 'end' => '2019-05-01', 'aid' => 21, 'sid' => 24, 'final_charge' => 0, 'full_price' => 0, 'billrun' => '201905', 'tax_data' => [], 'plan' => 'PLAN2'],
					['usaget' => 'flat', 'type' => 'service', 'start' => "2019-04-01", 'end' => '2019-05-01', 'aid' => 21, 'sid' => 24, 'final_charge' => 351, 'full_price' => 300, 'billrun' => '201905', 'tax_data' => [	"total_amount" => 0,"total_tax" => 0,"taxes" => [ ]], 'service' => 'SERVICE2'],
			['usaget' => 'flat', 'type' => 'flat', 'start' => "2019-04-01", 'end' => '2019-05-01', 'aid' => 21, 'sid' => 23, 'final_charge' => 0, 'full_price' => 0, 'billrun' => '201905', 'tax_data' => [], 'plan' => 'PLAN2'],
					['usaget' => 'flat', 'type' => 'service', 'start' => "2019-04-01", 'end' => '2019-05-01', 'aid' => 21, 'sid' => 23, 'final_charge' => 351, 'full_price' => 300, 'billrun' => '201905', 'tax_data' => [	"total_amount" => 0,"total_tax" => 0,"taxes" => [ ]], 'service' => 'SERVICE2'],
					['usaget' => 'flat', 'type' => 'flat', 'start' => "2019-04-01", 'end' => '2019-05-01', 'aid' => 21, 'sid' => 22, 'final_charge' => 0, 'full_price' => 0, 'billrun' => '201905', 'tax_data' => [], 'plan' => 'PLAN2'],
					['usaget' => 'flat', 'type' => 'service', 'start' => "2019-04-01", 'end' => '2019-05-01', 'aid' => 21, 'sid' => 22, 'final_charge' => 351, 'full_price' => 300, 'billrun' => '201905', 'tax_data' => [	"total_amount" => 0,"total_tax" => 0,"taxes" => [ ]], 'service' => 'SERVICE2']],
				'function' => array()),
			'expected' => array(
			),
			'subjectExpected' => [
				['sid' => '23', 'key' => 'DIS1', 'full_price' => -18, 'billrun' => '201905', 'service' => 'SERVICE2', 'affected_sections' => ['service']],
				['sid' => '22', 'key' => 'DIS1', 'full_price' => -18, 'billrun' => '201905', 'service' => 'SERVICE2', 'affected_sections' => ['service']],
				['sid' => '24', 'key' => 'DIS1', 'full_price' => -18, 'billrun' => '201905',  'service' => 'SERVICE2', 'affected_sections' => ['service']],
			]
		),
		/* Service
		 * three  Subscribers  eligible to two discounts:
 		 * DIS1 (sequential) from  12-29, DIS2 (sequential) from  10-20
		 */
		array('test_num' => 61, 'test' => array('options' => ['stamp' => '201905'], 'subsAccount' => [['aid' => 21]], 'subsRevisions' => 
				[
					[['sid' => 22, 'plan' => 'PLAN2', 'from' => '2019-04-01', 'to' => '2019-05-01', 'plan_activation' => "2019-04-01"]],
					[['sid' => 23, 'plan' => 'PLAN2', 'from' => '2019-04-01', 'to' => '2019-05-01', 'plan_activation' => "2019-04-01"]],
					[['sid' => 24, 'plan' => 'PLAN2', 'from' => '2019-04-01', 'to' => '2019-05-01', 'plan_activation' => "2019-04-01"]],
				],
				'discounts' => [
					['name' => 'DIS1', 'root' => ['from' => '2019-04-12', 'to' => '2019-04-30', 'type' => 'percentage', 'subject' => ['service' => ['SERVICE2' => ['value' => 0.1, 'sequential' => true]]]],
						'params_override' => [
							'condition' => [],
						]],
					['name' => 'DIS2', 'root' => ['from' => '2019-04-10', 'to' => '2019-04-21', 'type' => 'percentage', 'subject' => ['service' => ['SERVICE2' => ['value' => 0.2, 'sequential' => true]]]],
						'params_override' => [
							'condition' => [],
						]],
				],
				'cdrs' => [
					['usaget' => 'flat', 'type' => 'flat', 'start' => "2019-04-01", 'end' => '2019-05-01', 'aid' => 21, 'sid' => 24, 'final_charge' => 0, 'full_price' => 0, 'billrun' => '201905', 'tax_data' => [], 'plan' => 'PLAN2'],
					['usaget' => 'flat', 'type' => 'service', 'start' => "2019-04-01", 'end' => '2019-05-01', 'aid' => 21, 'sid' => 24, 'final_charge' => 351, 'full_price' => 300, 'billrun' => '201905', 'tax_data' => [	"total_amount" => 0,"total_tax" => 0,"taxes" => [ ]], 'service' => 'SERVICE2'],
					['usaget' => 'flat', 'type' => 'flat', 'start' => "2019-04-01", 'end' => '2019-05-01', 'aid' => 21, 'sid' => 23, 'final_charge' => 0, 'full_price' => 0, 'billrun' => '201905', 'tax_data' => [], 'plan' => 'PLAN2'],
					['usaget' => 'flat', 'type' => 'service', 'start' => "2019-04-01", 'end' => '2019-05-01', 'aid' => 21, 'sid' => 23, 'final_charge' => 351, 'full_price' => 300, 'billrun' => '201905', 'tax_data' => [	"total_amount" => 0,"total_tax" => 0,"taxes" => [ ]], 'service' => 'SERVICE2'],
					['usaget' => 'flat', 'type' => 'flat', 'start' => "2019-04-01", 'end' => '2019-05-01', 'aid' => 21, 'sid' => 22, 'final_charge' => 0, 'full_price' => 0, 'billrun' => '201905', 'tax_data' => [], 'plan' => 'PLAN2'],
					['usaget' => 'flat', 'type' => 'service', 'start' => "2019-04-01", 'end' => '2019-05-01', 'aid' => 21, 'sid' => 22, 'final_charge' => 351, 'full_price' => 300, 'billrun' => '201905', 'tax_data' => [	"total_amount" => 0,"total_tax" => 0,"taxes" => [ ]], 'service' => 'SERVICE2']],
				'function' => array()),
			'expected' => array(
			),
			'subjectExpected' => [
				['sid' => '23', 'key' => 'DIS1', 'full_price' => -18, 'billrun' => '201905', 'service' => 'SERVICE2', 'affected_sections' => ['service']],
				['sid' => '22', 'key' => 'DIS1', 'full_price' => -18, 'billrun' => '201905', 'service' => 'SERVICE2', 'affected_sections' => ['service']],
				['sid' => '24', 'key' => 'DIS1', 'full_price' => -18, 'billrun' => '201905',  'service' => 'SERVICE2', 'affected_sections' => ['service']],
				['sid' => '23', 'key' => 'DIS2', 'full_price' => -20.2, 'billrun' => '201905', 'service' => 'SERVICE2', 'affected_sections' => ['service']],
				['sid' => '22', 'key' => 'DIS2', 'full_price' => -20.2, 'billrun' => '201905', 'service' => 'SERVICE2', 'affected_sections' => ['service']],
				['sid' => '24', 'key' => 'DIS2', 'full_price' => -20.2, 'billrun' => '201905',  'service' => 'SERVICE2', 'affected_sections' => ['service']],
			]
		),
		/* PLAN
		 * Subscriber is eligible to three sequential discounts for a full cycle
		 * the second is not a percentage discount (ignore the sequential for this discount)
		 * + more then 100%
		 */
		array('test_num' => 62, 'test' => array('options' => ['stamp' => '201905'], 'subsAccount' => [['aid' => 21]], 'subsRevisions' => [
					[['sid' => 22, 'plan' => 'PLAN2', 'from' => '2019-04-01', 'to' => '2019-05-01', 'plan_activation' => "2019-04-01"],
					]],
				'discounts' => [
					['name' => 'DIS3', 'root' => ['type' => 'percentage', 'subject' => ['plan' => ['PLAN2' => ['value' => 0.5, 'sequential' => true]]]],
						'params_override' => [
							'condition' => [[
								['type' => 'subscriber', 'field' => 'plan', 'values' => 'PLAN2'],
								]],
						]],
					['name' => 'DIS1', 'root' => ['type' => 'monetary', 'subject' => ['plan' => ['PLAN2' => ['value' => 60, 'sequential' => true]]]],
						'params_override' => [
							'condition' => [[
								['type' => 'subscriber', 'field' => 'plan', 'values' => 'PLAN2'],
								]],
						]],
					['name' => 'DIS2', 'root' => ['type' => 'percentage', 'subject' => ['plan' => ['PLAN2' => ['value' => 0.2, 'sequential' => true]]]],
						'params_override' => [
							'condition' => [[
								['type' => 'subscriber', 'field' => 'plan', 'values' => 'PLAN2'],
								]],
						]],
				],
				'cdrs' => [
					['usaget' => 'flat', 'type' => 'flat', 'start' => "2019-04-01", 'end' => '2019-05-01', 'aid' => 21, 'sid' => 22, 'final_charge' => 117, 'full_price' => 100, 'billrun' => '201905', 'tax_data' => [], 'plan' => 'PLAN2']],
				'function' => array()),
			'expected' => array(
			),
			'subjectExpected' => [
				['sid' => '22', 'key' => 'DIS1', 'full_price' => -50, 'billrun' => '201905', 'final_charge' => -58.5,  'plan' => 'PLAN2', 'affected_sections' => ['plan']],
				['sid' => '22', 'key' => 'DIS3', 'full_price' => -50, 'billrun' => '201905', 'final_charge' => -58.5, 'plan' => 'PLAN2', 'affected_sections' => ['plan']],
			]
		),
	];

}
