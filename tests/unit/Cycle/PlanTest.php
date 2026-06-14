<?php

class PlanTest extends \Codeception\Test\Unit {
	/**
	 * @var \UnitTester
	 */
	protected $tester;

	protected function _before() {
	}

	protected function _after()	{
	}

	// tests
	public function testgetPriceByTariff() {
		$tariffs = [
				'singleLvlLimited' => [	['from' => 0, 'to' => 3, 'price' => 10]	],
				'singleLvl' => [	['from' => 0, 'to' => 'UNLIMITED', 'price' => 10]	],
				'twoLvl' => [	['from' => 0, 'to' => 3, 'price' => 1],
								['from' => 3, 'to' => "UNLIMITED", 'price' => 10]	],
				'threeLvl' => [	['from' => 0, 'to' => 3, 'price' => 1],
								['from' => 3, 'to' => 6, 'price' => 10],
								['from' => 6, 'to' => "UNLIMITED", 'price' => 100]	],
				'threeLvlLimited' => [	['from' => 0, 'to' => 3, 'price' => 1],
										['from' => 3, 'to' => 6, 'price' => 10],
										['from' => 6, 'to' => "12", 'price' => 100]	],
				];
		$tests  =[
			// Verifies that the getPriceByTariff method returns the correct price for a single-level limited tariff when the start and end offsets are within the tariff's range.
			'test1' => [ 'start'=> 0 , 'end'=> 1, 'activation' => strtotime('2025-01-01') , 'priceType' =>  'singleLvlLimited', 'tariffIdx' => 0,
						'res' => ['price' => 10, 'start' => FALSE, 'end' => FALSE, 'multiplier' => 1 ] ],
			// Tests that the method returns 0 when the start or end offset falls outside the tariff's range,
			'test2' => [ 'start'=> 3 , 'end'=> 4, 'activation' => strtotime('2025-01-01') , 'priceType' =>  'singleLvlLimited', 'tariffIdx' => 0,
						'res' => 0 ],
			// Checks that the method correctly handles a single-level unlimited tariff and returns the correct price for the full month if both the start and end offsets fall within the same month.
			'test3' => [ 'start'=> 3 , 'end'=> 4, 'activation' => strtotime('2025-01-01') , 'priceType' =>  'singleLvl', 'tariffIdx' => 0,
						'res' => ['price' => 10, 'start' => FALSE, 'end' => FALSE, 'multiplier' => 1 ] ],
			// Verifies that the method returns 0  price on a teir that in actually in the next month when the next month has less dayo then the acitvation month
			'test4' => [ 'start'=> 2 , 'end'=> 3, 'activation' => strtotime('2024-10-01') , 'priceType' =>  'twoLvl', 'tariffIdx' => 1,
						'res' => ['price' => 0, 'start' => FALSE, 'end' => FALSE, 'multiplier' => 0 ] ],
			// Verifies that the method returns 0 price on a teir that in actually in the next month when the current month has less dayo then the acitvation month
			// (Currently fail due to : https://billrun.atlassian.net/browse/BRCD-4803 )
			// 'test5' =>[ 'start'=> 2 , 'end'=> 3, 'activation' => strtotime('2024-11-01') , 'priceType' =>  'twoLvl', 'tariffIdx' => 1,
			// 			'res' => ['price' => 0, 'start' => FALSE, 'end' => FALSE ] ],
			'test6' => [ 'start' => 3, 'end' => 4, 'activation' => strtotime('2025-01-01'), 'priceType' => 'singleLvlLimited', 'tariffIdx' => 0,
						'res' => 0 ],
			'test7' => [ 'start' => 3.5, 'end' => 4.5, 'activation' => strtotime('2025-01-15'), 'priceType' => 'singleLvlLimited', 'tariffIdx' => 0,
						'res' => 0 ],
			'test8' => [ 'start' => 3, 'end' => 4, 'activation' => strtotime('2025-02-01'), 'priceType' => 'twoLvl', 'tariffIdx' => 1,
						 'res' => ['price' => 10, 'start' => FALSE, 'end' => FALSE, 'multiplier' => 1 ] ],
			'test9' => [ 'start' => 0.9677419354838710, 'end' => 1, 'activation' => strtotime('2025-01-31'), 'priceType' => 'singleLvl', 'tariffIdx' => 0,
						 'res' => ['price' => 0.32258064516129004, 'start' => 0.9677419354838710, 'end' => 1, 'multiplier' => 0.032258064516129004 ] ],
			'test10' => [ 'start' => 1.5, 'end' => 2.5, 'activation' => strtotime('2025-01-15'), 'priceType' => 'singleLvlLimited', 'tariffIdx' => 0,
						  'res' => ['price' => 10, 'start' => FALSE, 'end' => FALSE, 'multiplier' => 1 ] ],
			'test11' => [ 'start' => 3.9677419354838710, 'end' => 4.9677419354838710, 'activation' => strtotime('2025-01-31'), 'priceType' => 'singleLvlLimited', 'tariffIdx' => 0,
						  'res' => 0 ],
			'test12' => [ 'start' => 1, 'end' => 2, 'activation' => strtotime('2025-01-15'), 'priceType' => 'singleLvlLimited', 'tariffIdx' => 0,
						  'res' => ['price' => 10, 'start' => FALSE, 'end' => FALSE, 'multiplier' => 1 ] ],
			'test13' => [ 'start' => 3.5, 'end' => 4.5, 'activation' => strtotime('2025-01-15'), 'priceType' => 'twoLvl', 'tariffIdx' => 0,
						  'res' => 0 ],
			'test14' => [ 'start' => 3.5, 'end' => 4.5, 'activation' => strtotime('2025-01-15'), 'priceType' => 'twoLvl', 'tariffIdx' => 1,
						  'res' => ['price' => 10, 'start' => FALSE, 'end' => FALSE, 'multiplier' => 1 ] ],
			'test15' => [ 'start' => 3.5, 'end' => 4.5, 'activation' => strtotime('2025-01-15'), 'priceType' => 'threeLvlLimited', 'tariffIdx' => 2,
						  'res' => 0 ],
			'test16' => [ 'start' => 6.5, 'end' => 7.5, 'activation' => strtotime('2025-01-15'), 'priceType' => 'threeLvlLimited', 'tariffIdx' => 2,
						  'res' => ['price' => 100, 'start' => FALSE, 'end' => FALSE, 'multiplier' => 1 ] ],
			'test17' => [ 'start' => 3.9677419354838710, 'end' => 4.9677419354838710, 'activation' => strtotime('2025-01-31'), 'priceType' => 'twoLvl', 'tariffIdx' => 0,
						  'res' => 0 ],
			'test18' => [ 'start' => 1.9677419354838710, 'end' => 2.9677419354838710, 'activation' => strtotime('2025-01-31'), 'priceType' => 'twoLvl', 'tariffIdx' => 0,
						  'res' => ['price' => 1, 'start' => FALSE, 'end' => FALSE, 'multiplier' => 1 ] ],
		];
		foreach( $tests as $tstKey => $tstVal) {
			$res = Billrun_Plan::getPriceByTariff( $tariffs[$tstVal['priceType']][$tstVal['tariffIdx']], $tstVal['start'], $tstVal['end'], $tstVal['activation'] );
			$this->assertEquals($tstVal['res'], $res, $tstKey);
		}
	}
}
