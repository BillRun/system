<?php

use PHPUnit\Framework\Assert;

/**
 * Discount condition with "value: true" (e.g. {field: plan, op: exists, value: true}) must not be detected
 * as a date range field. The loose in_array() matched true to 'active', which made the cycle split the
 * subscriber revision by "plan" and leave plan as an empty array; on PHP 8 the plans eligibility then
 * crashed with "Cannot access offset of type array in isset or empty".
 */
class DiscountDateRangeFieldsCest {

	protected $billrunKey = '202612';

	public function _before(UnitTester $I) {
		Billrun_Factory::config();
		$this->resetDiscountManagerCache();
	}

	public function _after(UnitTester $I) {
		$this->resetDiscountManagerCache();
	}

	public function planExistsIsNotDateRangeField(UnitTester $I) {
		$this->seedDiscounts([
			$this->getDiscount('PLAN_EXISTS', [['field' => 'plan', 'op' => 'exists', 'value' => true]]),
			$this->getDiscount('SERVICE_ACTIVE', [['field' => 'my_date_range', 'op' => 'in', 'value' => 'active']]),
		]);

		$fields = array_values(Billrun_DiscountManager::getDiscountsDateRangeFields($this->billrunKey, 'subscriber'));

		Assert::assertEquals(['my_date_range'], $fields, 'only "active"/"notActive" values are date range fields');
	}

	public function planExistsDiscountDoesNotBreakEligibility(UnitTester $I) {
		$this->seedDiscounts([
			$this->getDiscount('PLAN_EXISTS', [['field' => 'plan', 'op' => 'exists', 'value' => true]]),
		]);
		$cycle = new Billrun_DataTypes_CycleTime($this->billrunKey);
		$from = new Mongodloid_Date($cycle->start());
		$to = new Mongodloid_Date($cycle->end());
		$accountRevisions = [
			['aid' => 1001, 'sid' => 0, 'type' => 'account', 'from' => $from, 'to' => $to],
		];
		$subscribersRevisions = [
			2001 => [
				[
					'aid' => 1001,
					'sid' => 2001,
					'type' => 'subscriber',
					'plan' => 'TEST_PLAN',
					'plan_activation' => $from,
					'from' => $from,
					'to' => $to,
					'services' => [],
				],
			],
		];

		// before the fix this throws TypeError: Cannot access offset of type array in isset or empty
		$dm = new Billrun_DiscountManager($accountRevisions, $subscribersRevisions, $cycle);
		$eligible = $dm->getEligibleDiscounts(true);

		Assert::assertArrayHasKey('PLAN_EXISTS', $eligible);
		Assert::assertArrayHasKey(2001, $eligible['PLAN_EXISTS']['subs']);
		Assert::assertArrayHasKey('TEST_PLAN', $eligible['PLAN_EXISTS']['plans'][2001] ?? [], 'plan eligibility is keyed by the plan name');
	}

	protected function getDiscount($key, $subscriberFields) {
		return [
			'key' => $key,
			'from' => new Mongodloid_Date(strtotime('-1 year')),
			'to' => new Mongodloid_Date(strtotime('+100 years')),
			'params' => [
				'conditions' => [
					['subscriber' => [['fields' => $subscriberFields]]],
				],
			],
		];
	}

	protected function seedDiscounts($discounts) {
		$seeded = [];
		foreach ($discounts as $discount) {
			$seeded[$discount['key']] = $discount;
		}
		$this->setStatic('discounts', [$this->billrunKey => $seeded]);
	}

	protected function resetDiscountManagerCache() {
		$this->setStatic('discounts', []);
		$this->setStatic('discountsDateRangeFields', []);
	}

	protected function setStatic($property, $value) {
		$prop = new ReflectionProperty('Billrun_DiscountManager', $property);
		if (PHP_VERSION_ID < 80100) {
			$prop->setAccessible(true);
		}
		$prop->setValue(null, $value);
	}
}
