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
			'test1' => [ 'start'=> 0 , 'end'=> 1, 'activation' => strtotime('2025-01-01') , 'priceType' =>  'singleLvlLimited', 'tariffIdx' => 0,
						'res' => ['price' => 10, 'start' => FALSE, 'end' => FALSE ] ],
			'test2' => [ 'start'=> 3 , 'end'=> 4, 'activation' => strtotime('2025-01-01') , 'priceType' =>  'singleLvlLimited', 'tariffIdx' => 0,
						'res' => 0 ],
			'test3' => [ 'start'=> 3 , 'end'=> 4, 'activation' => strtotime('2025-01-01') , 'priceType' =>  'singleLvl', 'tariffIdx' => 0,
						'res' => ['price' => 10, 'start' => FALSE, 'end' => FALSE ] ],
			'test4' => [ 'start'=> 2 , 'end'=> 3, 'activation' => strtotime('2024-12-01') , 'priceType' =>  'twoLvl', 'tariffIdx' => 1,
						'res' => ['price' => 0, 'start' => FALSE, 'end' => FALSE ] ],
		];
		foreach( $tests as $tstKey => $tstVal) {
			$res = Billrun_Plan::getPriceByTariff( $tariffs[$tstVal['priceType']][$tstVal['tariffIdx']], $tstVal['start'], $tstVal['end'], $tstVal['activation'] );
			$this->assertEquals($tstVal['res'], $res);
		}
	}
}
