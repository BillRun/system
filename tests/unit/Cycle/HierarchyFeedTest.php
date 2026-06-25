<?php

/**
 * BRCD-785 customer hierarchy in external subscribers mode - unit coverage for the feed-backed resolver
 * (Billrun_Cycle_Hierarchy_Feed). The resolver answers every hierarchy question from the in-memory account
 * feed the CRM returned for a page, with NO access to the local subscribers collection, so these tests seed
 * a tree directly and assert the same answers the Mongo-backed resolver would give for the same shape.
 */
class HierarchyFeedTest extends \Codeception\Test\Unit {

	/**
	 * @var \UnitTester
	 */
	protected $tester;

	/**
	 * Build a feed-backed resolver from a compact tree spec.
	 *
	 * @param array $specs aid => ['parent_aid'=>?, 'issues_invoice'=>?, 'is_payer'=>?, 'from'=>?] (any omitted
	 *                     field exercises the resolver's default). A value of array of revisions can be passed
	 *                     under 'revisions' to seed multiple sid=0 account revisions for one aid.
	 * @return Billrun_Cycle_Hierarchy_Feed
	 */
	protected function feed(array $specs) {
		$accounts = array();
		foreach ($specs as $aid => $fields) {
			if (isset($fields['revisions'])) {
				$revisions = $fields['revisions'];
			} else {
				$revisions = array(array_merge(array('aid' => $aid, 'sid' => 0, 'type' => 'account'), $fields));
			}
			$accounts[(string) $aid] = array(
				'attributes' => array('aid' => $aid),
				'subscribers' => array(0 => $revisions),
			);
		}
		return new Billrun_Cycle_Hierarchy_Feed(new Billrun_DataTypes_CycleTime('202601'), $accounts);
	}

	/**
	 * A 3-level tree with a mid-tree invoicing boundary:
	 *   100 (root, invoicing, payer)
	 *    |- 200 (rolls up; not payer)
	 *    |   \- 300 (rolls up; not payer)
	 *    \- 400 (issues_invoice=true -> its OWN unit; not payer)
	 *        \- 500 (rolls up into 400; not payer)
	 *   600 (separate root, all defaults -> invoicing + payer)
	 */
	protected function sampleTree() {
		return $this->feed(array(
			100 => array('parent_aid' => null, 'issues_invoice' => true,  'is_payer' => true),
			200 => array('parent_aid' => 100,  'issues_invoice' => false, 'is_payer' => false),
			300 => array('parent_aid' => 200,  'issues_invoice' => false, 'is_payer' => false),
			400 => array('parent_aid' => 100,  'issues_invoice' => true,  'is_payer' => false),
			500 => array('parent_aid' => 400,  'issues_invoice' => false, 'is_payer' => false),
			600 => array(),
		));
	}

	public function testIsInvoicingAccount() {
		$h = $this->sampleTree();
		$this->assertTrue($h->isInvoicingAccount(100), 'root is invoicing');
		$this->assertFalse($h->isInvoicingAccount(200), 'issues_invoice=false rolls up');
		$this->assertFalse($h->isInvoicingAccount(300));
		$this->assertTrue($h->isInvoicingAccount(400), 'issues_invoice=true starts its own unit');
		$this->assertFalse($h->isInvoicingAccount(500));
		$this->assertTrue($h->isInvoicingAccount(600), 'default (no parent, no issues_invoice) is invoicing');
	}

	public function testGetInvoiceOwner() {
		$h = $this->sampleTree();
		$this->assertSame(100, $h->getInvoiceOwner(100));
		$this->assertSame(100, $h->getInvoiceOwner(200));
		$this->assertSame(100, $h->getInvoiceOwner(300), 'grandchild rolls up to the root invoicing account');
		$this->assertSame(400, $h->getInvoiceOwner(400));
		$this->assertSame(400, $h->getInvoiceOwner(500), 'rolls up to the nearest invoicing ancestor, not the root');
		$this->assertSame(600, $h->getInvoiceOwner(600));
	}

	public function testGetParentAid() {
		$h = $this->sampleTree();
		$this->assertNull($h->getParentAid(100));
		$this->assertSame(100, $h->getParentAid(200));
		$this->assertSame(200, $h->getParentAid(300));
		$this->assertSame(400, $h->getParentAid(500));
		$this->assertNull($h->getParentAid(600));
	}

	public function testGetChildAids() {
		$h = $this->sampleTree();
		$this->assertEquals(array(200, 400), $this->sorted($h->getChildAids(100)));
		$this->assertEquals(array(300), $this->sorted($h->getChildAids(200)));
		$this->assertEquals(array(500), $this->sorted($h->getChildAids(400)));
		$this->assertEquals(array(), $this->sorted($h->getChildAids(300)));
	}

	public function testGetRollupDescendantsStopsAtInvoicingBoundary() {
		$h = $this->sampleTree();
		// 400 issues its own invoice, so neither 400 nor 500 roll up into 100.
		$this->assertEquals(array(200, 300), $this->sorted($h->getRollupDescendants(100)));
		$this->assertEquals(array(500), $this->sorted($h->getRollupDescendants(400)));
		$this->assertEquals(array(), $this->sorted($h->getRollupDescendants(600)));
	}

	public function testGetSubtreeAids() {
		$h = $this->sampleTree();
		$this->assertEquals(array(100, 200, 300), $this->sorted($h->getSubtreeAids(100)));
		$this->assertEquals(array(400, 500), $this->sorted($h->getSubtreeAids(400)));
		$this->assertEquals(array(600), $this->sorted($h->getSubtreeAids(600)));
	}

	public function testGetPayerAidWalksToNearestPayerAncestor() {
		$h = $this->sampleTree();
		$this->assertSame(100, $h->getPayerAid(100), 'a payer pays itself');
		$this->assertSame(100, $h->getPayerAid(200), 'non-payer walks up to the root payer');
		$this->assertSame(100, $h->getPayerAid(300), 'two hops up to the root payer');
		$this->assertSame(100, $h->getPayerAid(400));
		$this->assertSame(100, $h->getPayerAid(500));
		$this->assertSame(600, $h->getPayerAid(600), 'default is_payer (absent) -> pays itself');
	}

	public function testDefaultsForMissingFields() {
		$h = $this->feed(array(
			700 => array('parent_aid' => null), // no issues_invoice / is_payer
		));
		$state = $h->getAccountState(700);
		$this->assertSame(700, $state['aid']);
		$this->assertNull($state['parent_aid']);
		$this->assertTrue($state['issues_invoice'], 'missing issues_invoice defaults to true');
		$this->assertTrue($state['is_payer'], 'missing is_payer defaults to true');
	}

	public function testAccountNotOnFeedIsTreatedAsInactive() {
		$h = $this->sampleTree();
		$this->assertFalse($h->getAccountState(999), 'unknown aid: no state from the feed');
		$this->assertFalse($h->isInvoicingAccount(999));
		$this->assertNull($h->getParentAid(999));
		$this->assertSame(999, $h->getInvoiceOwner(999), 'orphan fallback: an account with no resolvable owner self-invoices');
	}

	public function testStringAndIntParentAidNormalization() {
		$h = $this->feed(array(
			10 => array('parent_aid' => null, 'issues_invoice' => true),
			20 => array('parent_aid' => '10', 'issues_invoice' => false), // parent_aid as string
		));
		$this->assertSame(10, $h->getParentAid(20));
		$this->assertSame(10, $h->getInvoiceOwner(20));
		$this->assertEquals(array(20), $this->sorted($h->getChildAids(10)));
	}

	public function testEmptyStringParentAidIsRoot() {
		$h = $this->feed(array(
			30 => array('parent_aid' => '', 'issues_invoice' => false), // empty string -> treated as root
		));
		$this->assertNull($h->getParentAid(30));
		$this->assertTrue($h->isInvoicingAccount(30), 'a rootless account is invoicing even with issues_invoice=false');
		$this->assertSame(30, $h->getInvoiceOwner(30));
	}

	public function testExtractAccountDataPicksLatestRevisionByFrom() {
		// Two account revisions overlap the cycle; the end-of-cycle (latest 'from') one wins, mirroring the
		// DB resolver's "latest revision" snapshot. Here the account is reparented from 100 to 800 mid-cycle.
		$h = $this->feed(array(
			800 => array('parent_aid' => null, 'issues_invoice' => true),
			100 => array('parent_aid' => null, 'issues_invoice' => true),
			900 => array('revisions' => array(
				array('aid' => 900, 'sid' => 0, 'type' => 'account', 'parent_aid' => 100, 'issues_invoice' => false, 'from' => 1000),
				array('aid' => 900, 'sid' => 0, 'type' => 'account', 'parent_aid' => 800, 'issues_invoice' => false, 'from' => 2000),
			)),
		));
		$this->assertSame(800, $h->getParentAid(900), 'latest revision parent wins');
		$this->assertSame(800, $h->getInvoiceOwner(900));
	}

	public function testExtractAccountDataStaticShape() {
		$accounts = array(
			'5' => array('subscribers' => array(0 => array(array('aid' => 5, 'parent_aid' => 1)))),
			'6' => array('subscribers' => array()),          // no account-level revision -> skipped
			'7' => array('attributes' => array('aid' => 7)), // no subscribers key -> skipped
		);
		$data = Billrun_Cycle_Hierarchy_Feed::extractAccountData($accounts);
		$this->assertArrayHasKey(5, $data);
		$this->assertArrayNotHasKey(6, $data);
		$this->assertArrayNotHasKey(7, $data);
		$this->assertSame(1, $data[5]['parent_aid']);
	}

	protected function sorted($arr) {
		$arr = array_map('intval', (array) $arr);
		sort($arr);
		return $arr;
	}
}
