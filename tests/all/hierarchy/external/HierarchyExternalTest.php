<?php

/**
 * BRCD-785 customer hierarchy in EXTERNAL subscribers mode - integration coverage through the CRM mockup.
 *
 * The CRM fixture crm_data/770001.json returns the invoicing owner (770001) together with its full subtree
 * (the non-invoicing sub-account 770002 and both accounts' subscribers) in one GBA response - the Option A
 * contract. With customer.hierarchy.external_enabled on, the aggregator must consolidate the sub-account's
 * subscriber into the owner's single invoice; with it off it must behave exactly as a flat tenant.
 *
 * Note: like the other tests/all external suites this drives a real cycle against the mockup CRM server and
 * the config API, so it runs in the docker integration environment.
 */
class HierarchyExternalTest extends \Codeception\Test\Unit {

	/**
	 * @var \UnitTester
	 */
	protected $tester;

	protected $epsilon = 0.00001;

	public $defaultOptions = array(
		"type" => "customer",
		"page" => 0,
		"size" => 100,
		'fetchonly' => true,
		'generate_pdf' => 0,
	);

	protected function _before() {
		ini_set('error_reporting', E_ALL & ~E_WARNING & ~E_NOTICE);
		$this->tester->cleanDB();
		$this->tester->setTimezone('UTC');
		$this->tester->enableExternalModeSettings();
		$this->tester->generatePlan(array(
			'name' => 'PLAN_HIER',
			'price' => array(array('price' => 100, 'from' => 0, 'to' => 'UNLIMITED')),
		));
	}

	protected function _after() {
		$this->setHierarchyEnabled(0);
		$this->tester->restoreTimezone();
		$this->tester->enableDBModeSettings();
	}

	/**
	 * Flip the external-hierarchy flag in the in-memory config (a merge, so the aggregator passthrough config
	 * is untouched) and clear the memoized isInUse() probe so the next resolver call sees the new value.
	 */
	protected function setHierarchyEnabled($enabled) {
		Billrun_Factory::config()->setConfigValue('customer.hierarchy.external_enabled', $enabled ? 1 : 0);
		Billrun_Cycle_Hierarchy::resetInUseCache();
	}

	/**
	 * Subscriber aids billed onto $ownerAid's invoice for $billrunKey. Subscriber records are not stored
	 * inline on the billrun document (Billrun_Cycle_Account_Invoice::_saveWithoutTransaction() strips
	 * 'subs' before saving the billrun doc and writes them to the separate billrun_subs collection
	 * instead); a rolled-up sub-account's subscriber keeps its own aid there and is tagged with
	 * parent_aid = the owner's aid by setSubscribers(), which is how a consolidated subscriber is told
	 * apart from one that owns its own, separate invoice.
	 *
	 * @param string $billrunKey
	 * @param int $ownerAid
	 * @return int[]
	 */
	protected function subAids($billrunKey, $ownerAid) {
		$cursor = Billrun_Factory::db()->billrun_subsCollection()->query(array(
			'key' => $billrunKey,
			'$or' => array(
				array('aid' => $ownerAid),
				array('parent_aid' => $ownerAid),
			),
		))->cursor();
		$aids = array();
		foreach ($cursor as $sub) {
			if (isset($sub['aid'])) {
				$aids[] = intval($sub['aid']);
			}
		}
		return $aids;
	}

	public function testSubAccountConsolidatedIntoInvoicingOwner() {
		$this->setHierarchyEnabled(1);
		$this->tester->runCycle(array_merge($this->defaultOptions, array(
			'stamp' => '202601',
			'force_accounts' => array(770001),
		)));

		$ownerBillrun = $this->tester->grabFromCollection('billrun', array('billrun_key' => '202601', 'aid' => 770001));
		$subBillrun = $this->tester->grabFromCollection('billrun', array('billrun_key' => '202601', 'aid' => 770002));

		$this->assertNotEmpty($ownerBillrun, 'the invoicing owner produces a billrun document');
		$this->assertEmpty($subBillrun, 'the non-invoicing sub-account produces no billrun document of its own');

		$subAids = $this->subAids('202601', 770001);
		$this->assertContains(770001, $subAids, 'the owner subscriber is on the invoice');
		$this->assertContains(770002, $subAids, 'the sub-account subscriber is rolled up into the owner invoice');
	}

	public function testFlagOffLeavesAccountsFlat() {
		$this->setHierarchyEnabled(0);
		$this->tester->runCycle(array_merge($this->defaultOptions, array(
			'stamp' => '202602',
			'force_accounts' => array(770001),
		)));

		$ownerBillrun = $this->tester->grabFromCollection('billrun', array('billrun_key' => '202602', 'aid' => 770001));
		$subBillrun = $this->tester->grabFromCollection('billrun', array('billrun_key' => '202602', 'aid' => 770002));

		// With hierarchy off the feed is treated as two independent accounts: each gets its own invoice and
		// the sub-account is NOT rolled up.
		$this->assertNotEmpty($ownerBillrun, 'owner gets its own invoice');
		$this->assertNotEmpty($subBillrun, 'sub-account gets its own invoice when hierarchy is disabled');
		$this->assertNotContains(770002, $this->subAids('202602', 770001), 'no consolidation when the flag is off');
	}
}
