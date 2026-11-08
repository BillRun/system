<?php

/**
 * Tests for subscriber discount calculation via Billrun_DiscountManager.
 *
 * BRCD-5033 — account-level discount must not override a subscriber's own discount
 * for the same key. When the subscriber's subject/params differ from the account's,
 * handleForceDiscountForSubRev creates a subscriber-specific key so the subscriber
 * gets their own value while other subscribers keep the account value.
 *
 * BRCD-5611 / SLTCH-1089 — same forced discount key, different 'from' dates across
 * revisions in one cycle must yield separate CDRs. Fix: append '_<from_ms>' to the
 * forced key so each revision version has its own eligibility entry.
 */
class DiscountTest extends \Codeception\Test\Unit {
	/**
	 * @var \UnitTester
	 */
	protected $tester;
	protected $isRun = false;

	private $defaultTimezone;

	protected function _before() {
		//Set the default timezone to UTC for the tests
		if (!$this->isRun) {
			$this->isRun = true;
			//load the config so we can override the timezone AFTER it was set by configuration
			Billrun_Factory::config();
			$this->defaultTimezone = date_default_timezone_get();
			date_default_timezone_set('UTC');
		}
	}

	protected function _after() {
	}

	public function testSubscriberOnTheFlyDiscounts() {
		$this->runDiscountTestCasesFromFile('discount_test_data.json', [
			'final_charge'     => 1,
			'tax_data'         => 1,
			'name'             => 1,
			'aprice'           => 1,
			'eligible_line'    => 1,
			'discount_subject' => 1,
			'discount_from'    => 1,
			'discount_to'      => 1,
		]);

		//Reset the timezone to the default one, put it on the last test
		date_default_timezone_set($this->defaultTimezone);
	}

	/**
	 * BRCD-5033 — when a subscriber holds the same discount key as the account but
	 * with a different value, the subscriber must receive their own value and the
	 * account value must remain intact for all other subscribers.
	 *
	 * Two sub-cases exercise this with matching and mismatched discount 'from' dates,
	 * confirming that the 'from' date difference does not affect the override logic.
	 */
	public function testAccountDiscountDoesNotOverrideSubscriberDiscount() {
		$this->runDiscountTestCasesFromFile('account_subscriber_discount_override_test_data.json', [
			'aprice'        => 1,
			'eligible_line' => 1,
		]);
	}

	/**
	 * BRCD-5611 / SLTCH-1089 — same forced discount key with different 'from' dates
	 * across revisions in one cycle must yield separate CDRs with the correct value each.
	 */
	public function testForcedDiscountMultiRevisionProducesSeparateCdrs() {
		$this->runDiscountTestCasesFromFile('forced_discount_multi_revision_test_data.json', [
			'aprice'        => 1,
			'eligible_line' => 1,
		]);
	}

	/**
	 * Loads all test cases from a JSON file under TestData/, runs generateCdrs for each,
	 * and asserts that the filtered CDR output matches the expected result.
	 */
	protected function runDiscountTestCasesFromFile(string $jsonFile, array $fieldsToCompare): void {
		$tests = json_decode(file_get_contents(__DIR__ . '/TestData/' . $jsonFile), true);

		foreach ($tests as $tstKey => $tstVal) {
			Billrun_Utils_Mongo::convertQueryMongoDates($tstVal['account_revs']);
			Billrun_Utils_Mongo::convertQueryMongoDates($tstVal['subscribers_revs']);
			$dm = new Billrun_DiscountManager(
				$tstVal['account_revs'],
				$tstVal['subscribers_revs'],
				new Billrun_DataTypes_CycleTime($tstVal['cycle'])
			);

			Billrun_Utils_Mongo::convertQueryMongoDates($tstVal['flat_lines']);
			$res = $dm->generateCdrs($tstVal['flat_lines']);

			Billrun_Utils_Mongo::convertQueryMongoDates($tstVal['result']);
			$filter = function ($v) use ($fieldsToCompare) {
				return array_intersect_key($v, $fieldsToCompare);
			};
			$this->assertEquals(
				array_map($filter, $tstVal['result']),
				array_map($filter, $res),
				$tstKey
			);
		}
	}
}
