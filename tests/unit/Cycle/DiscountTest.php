<?php

class DiscountTest extends \Codeception\Test\Unit {
	/**
	 * @var \UnitTester
	 */
	protected $tester;
	protected $isRun =false;

	private $defaultTimezone;

	protected function _before() {
		//Set  the  default timezone to UTC  for  the tests
		if(!$this->isRun) {
			$this->isRun = true;
			//load the  config so  we  can  ovverride the  timezone AFTER  it  waas  set by configuration
			Billrun_Factory::config();
			$this->defaultTimezone = date_default_timezone_get();
			date_default_timezone_set('UTC');
		}
	}

	protected function _after()	{
	}

	// tests
	public function testSubscriberOnTheFlyDiscounts() {
		$tests =  json_decode(file_get_contents(__DIR__."/TestData/discount_test_data.json"),true);
		$resDIscountsFieldsToCompare = [
			'final_charge' => 1,
			'tax_data' => 1,
			'tax_data' => 1,
			'name' => 1,
			'aprice' => 1,
			'eligible_line' => 1,
			'discount_subject' => 1,
			'discount_from' => 1,
			'discount_to' => 1
		];

		foreach( $tests as $tstKey => $tstVal) {
			//Setup  the  eligiblity based on the account and subscribers revisions
			Billrun_Utils_Mongo::convertQueryMongoDates($tstVal['account_revs']);
			Billrun_Utils_Mongo::convertQueryMongoDates($tstVal['subscribers_revs']);
			$dm = new Billrun_DiscountManager(	$tstVal['account_revs'],
												$tstVal['subscribers_revs'],
												new Billrun_DataTypes_CycleTime( $tstVal['cycle']) );

			// Generate  the  Cdrs  based on  the flat lines  provided
			Billrun_Utils_Mongo::convertQueryMongoDates($tstVal['flat_lines']);
			$res = $dm->generateCdrs($tstVal['flat_lines']);

			// Covert Stirng dates to mongo  dates and Filter out unrelated  fields
			Billrun_Utils_Mongo::convertQueryMongoDates($tstVal['result']);
			$tstVal['result'] = array_map(function($v) use ($resDIscountsFieldsToCompare) {
				return array_intersect_key($v, $resDIscountsFieldsToCompare);
			},$tstVal['result']);
			$res = array_map(function($v) use ($resDIscountsFieldsToCompare) {
				return array_intersect_key($v, $resDIscountsFieldsToCompare);
			},$res);

			$this->assertEquals($tstVal['result'], $res, $tstKey);
		}
	
		//Reset the timezone to the default one,put it on the last test
		date_default_timezone_set($this->defaultTimezone );


	}
}
