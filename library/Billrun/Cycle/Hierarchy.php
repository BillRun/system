<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2026 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Customer-hierarchy resolver for a single billing cycle (BRCD-785).
 *
 * Resolves each account's hierarchy state (parent_aid / issues_invoice / is_payer) from the account
 * revision active at the *last second of the cycle* (the same revision the aggregator uses as
 * accountLastRevision), so the whole tree is resolved from one deterministic end-of-cycle snapshot.
 *
 * This single helper is the source of truth shared by the aggregate selection (getBillable), the
 * roll-up bucketing (parseToAccounts) and the re-run cleanup (removeBeforeAggregate), so they can never
 * disagree about which descendants belong to which invoicing account.
 *
 * An "invoicing account" is one whose end-of-cycle revision has no parent (parent_aid null/absent) OR
 * issues_invoice=true; defaults for missing fields are parent_aid => root, issues_invoice/is_payer => true.
 *
 * Resolution is lazy and cached per instance; create one instance per cycle/page.
 *
 * @package  Cycle
 * @since    5.26
 */
class Billrun_Cycle_Hierarchy {

	/**
	 * Cycle window start, unix seconds.
	 * @var int
	 */
	protected $startSec;

	/**
	 * Cycle window end (last second of the cycle), unix seconds.
	 * @var int
	 */
	protected $endSec;

	/**
	 * Maximum hierarchy depth (levels). Bounds all walks against corrupted/looping data.
	 * @var int
	 */
	protected $maxDepth;

	/**
	 * @var Mongodloid_Collection
	 */
	protected $collection;

	/**
	 * Cached "overlapping the cycle" query fragment.
	 * @var array
	 */
	protected $overlapQuery;

	/**
	 * aid => state array | false (false = no account revision active in the cycle).
	 * @var array
	 */
	protected $stateCache = array();

	/**
	 * aid => list of child aids active in the cycle whose end-of-cycle revision points to this aid.
	 * @var array
	 */
	protected $childrenCache = array();

	/**
	 * aid => resolved invoice-owner aid.
	 * @var array
	 */
	protected $ownerCache = array();

	/**
	 * Process-lifetime memo for isInUse().
	 * @var bool|null
	 */
	protected static $hierarchyInUseCache = null;

	/**
	 * @param Billrun_DataTypes_CycleTime|Billrun_DataTypes_MongoCycleTime $cycle
	 * @param int|null $maxDepth defaults to config customer.hierarchy.max_depth (4)
	 */
	public function __construct($cycle, $maxDepth = null) {
		$start = $cycle->start();
		$end = $cycle->end();
		$this->startSec = is_object($start) ? $start->sec : $start;
		$this->endSec = is_object($end) ? $end->sec : $end;
		$this->maxDepth = is_null($maxDepth) ? self::getMaxDepth() : intval($maxDepth);
		$this->collection = Billrun_Factory::db()->subscribersCollection();
		$this->overlapQuery = Billrun_Utils_Mongo::getOverlappingWithRange('from', 'to', $this->startSec, $this->endSec);
	}

	/**
	 * Configured maximum customer-hierarchy depth (number of levels). Single source of truth for the
	 * customer.hierarchy.max_depth config read, shared by the aggregate, validation, generate and payment
	 * paths.
	 *
	 * @return int
	 */
	public static function getMaxDepth() {
		return intval(Billrun_Factory::config()->getConfigValue('customer.hierarchy.max_depth', 4));
	}

	/**
	 * Whether any account uses customer hierarchy (has a non-null parent_aid). Memoized for the lifetime
	 * of the PHP process - the single shared probe used by getBillable, the aggregator and the re-run
	 * cleanup so they don't each query the DB per page/call. Flat tenants thus pay one probe per process.
	 *
	 * @return bool
	 */
	public static function isInUse() {
		if (is_null(self::$hierarchyInUseCache)) {
			if (self::isExternalMode()) {
				// External subscribers mode: accounts live in a 3rd-party CRM, never in the local
				// subscribers collection, so there is nothing to probe. Hierarchy is driven by an explicit
				// config flag instead (the CRM still has to honor the subtree contract in its GBA/GAD feed).
				self::$hierarchyInUseCache = (bool) Billrun_Factory::config()->getConfigValue('customer.hierarchy.external_enabled', false);
			} else {
				$record = Billrun_Factory::db()->subscribersCollection()
					->query(array('type' => 'account', 'parent_aid' => array('$exists' => true, '$ne' => null)))
					->cursor()->limit(1)->current();
				self::$hierarchyInUseCache = ($record && !$record->isEmpty());
			}
		}
		return self::$hierarchyInUseCache;
	}

	/**
	 * Whether accounts are sourced from a 3rd-party CRM (external subscribers mode) rather than the local
	 * Mongo subscribers collection. Determines whether hierarchy state is resolved from the DB or from the
	 * in-memory account feed.
	 *
	 * @return bool
	 */
	public static function isExternalMode() {
		return strtolower(Billrun_Factory::config()->getConfigValue('subscribers.account.type', 'db')) === 'external';
	}

	/**
	 * Invalidate the isInUse() memo. Call after a write that may introduce (or remove) hierarchy in the
	 * same PHP process - e.g. creating an account with a parent_aid via the API, or in test setup/teardown -
	 * so a subsequent isInUse() re-probes the DB.
	 */
	public static function resetInUseCache() {
		self::$hierarchyInUseCache = null;
	}

	/**
	 * Hierarchy state of an account from its end-of-cycle revision, or false when the account has no
	 * revision active in the cycle.
	 *
	 * @param int $aid
	 * @return array|false ['aid','parent_aid'(int|null),'issues_invoice'(bool),'is_payer'(bool)]
	 */
	public function getAccountState($aid) {
		$aid = intval($aid);
		if (array_key_exists($aid, $this->stateCache)) {
			return $this->stateCache[$aid];
		}
		return $this->stateCache[$aid] = $this->loadAccountState($aid);
	}

	/**
	 * Load (uncached) the end-of-cycle hierarchy state of an account from its source. The DB resolver reads
	 * the latest overlapping account revision from Mongo; the feed-backed resolver overrides this to read
	 * from the in-memory account map built from the CRM subtree. Returns false when the account has no
	 * revision active in the cycle.
	 *
	 * @param int $aid
	 * @return array|false
	 */
	protected function loadAccountState($aid) {
		$query = array_merge(array('aid' => $aid, 'type' => 'account'), $this->overlapQuery);
		$revision = $this->collection->query($query)->cursor()->sort(array('from' => -1))->limit(1)->current();
		if (!$revision || $revision->isEmpty()) {
			return false;
		}
		return $this->normalizeState($aid, $revision->getRawData());
	}

	/**
	 * Build the canonical hierarchy-state array from a raw account revision. Shared by the DB and feed
	 * resolvers so both apply identical parent_aid/issues_invoice/is_payer defaulting.
	 *
	 * @param int $aid
	 * @param array $raw raw account revision data
	 * @return array ['aid','parent_aid'(int|null),'issues_invoice'(bool),'is_payer'(bool)]
	 */
	protected function normalizeState($aid, $raw) {
		$parentAid = (isset($raw['parent_aid']) && $raw['parent_aid'] !== '' && !is_null($raw['parent_aid']))
			? intval($raw['parent_aid'])
			: null;
		return array(
			'aid' => intval($aid),
			'parent_aid' => $parentAid,
			'issues_invoice' => $this->resolveBool($raw, 'issues_invoice', true),
			'is_payer' => $this->resolveBool($raw, 'is_payer', true),
		);
	}

	/**
	 * Whether the account issues its own invoice (root or issues_invoice=true). False when the account is
	 * not active in the cycle.
	 *
	 * @param int $aid
	 * @return bool
	 */
	public function isInvoicingAccount($aid) {
		$state = $this->getAccountState($aid);
		if ($state === false) {
			return false;
		}
		return is_null($state['parent_aid']) || $state['issues_invoice'] === true;
	}

	/**
	 * The invoicing account that owns this account's charges: the nearest self-or-ancestor that issues an
	 * invoice. Orphan fallback (dangling/inactive parent or no invoicing ancestor within bounds): the
	 * deepest reachable active account self-invoices, logged as an ALERT so charges are never lost.
	 *
	 * @param int $aid
	 * @return int owner aid
	 */
	public function getInvoiceOwner($aid) {
		$aid = intval($aid);
		if (array_key_exists($aid, $this->ownerCache)) {
			return $this->ownerCache[$aid];
		}
		$visited = array();
		$current = $aid;
		$lastActive = $aid;
		$hops = 0;
		while (!is_null($current) && !isset($visited[$current]) && $hops <= $this->maxDepth) {
			$visited[$current] = true;
			$state = $this->getAccountState($current);
			if ($state === false) {
				break; // current ancestor not active in the cycle
			}
			$lastActive = $current;
			if (is_null($state['parent_aid']) || $state['issues_invoice'] === true) {
				return $this->ownerCache[$aid] = $current;
			}
			$current = $state['parent_aid'];
			$hops++;
		}
		Billrun_Factory::log("Hierarchy: no invoicing ancestor resolved for account {$aid}; falling back to account {$lastActive}", Zend_Log::ALERT);
		return $this->ownerCache[$aid] = $lastActive;
	}

	/**
	 * Descendant aids that roll up into the given invoicing account: every descendant reachable through
	 * parent_aid that does not itself issue an invoice (the walk stops at any descendant that is itself an
	 * invoicing account - it starts its own unit).
	 *
	 * @param int $invoicingAid
	 * @return int[]
	 */
	public function getRollupDescendants($invoicingAid) {
		$result = array();
		$this->collectRollupDescendants(intval($invoicingAid), $result, 0);
		return array_keys($result);
	}

	protected function collectRollupDescendants($aid, &$result, $depth) {
		// Strictly-greater bound: a pure runaway guard against corrupted/cyclic child data. It is deliberately
		// looser than the validator's total-depth limit so it never under-collects legitimate descendants.
		if ($depth > $this->maxDepth) {
			return;
		}
		foreach ($this->getChildAids($aid) as $childAid) {
			if ($childAid === $aid || isset($result[$childAid])) {
				continue;
			}
			if ($this->isInvoicingAccount($childAid)) {
				continue; // child starts its own invoice unit
			}
			$result[$childAid] = true;
			$this->collectRollupDescendants($childAid, $result, $depth + 1);
		}
	}

	/**
	 * The full set of aids aggregated into one invoice: the invoicing account plus its rollup descendants.
	 *
	 * @param int $invoicingAid
	 * @return int[]
	 */
	public function getSubtreeAids($invoicingAid) {
		return array_merge(array(intval($invoicingAid)), $this->getRollupDescendants($invoicingAid));
	}

	/**
	 * Child aids active in the cycle whose end-of-cycle revision points to $aid as their parent.
	 *
	 * @param int $aid
	 * @return int[]
	 */
	public function getChildAids($aid) {
		$aid = intval($aid);
		if (array_key_exists($aid, $this->childrenCache)) {
			return $this->childrenCache[$aid];
		}
		return $this->childrenCache[$aid] = $this->loadChildAids($aid);
	}

	/**
	 * Load (uncached) the child aids of an account from its source. The DB resolver queries Mongo for
	 * account revisions pointing at $aid; the feed-backed resolver overrides this to read from the
	 * in-memory account map. Each candidate is re-checked against its end-of-cycle state so a child that
	 * was reparented mid-cycle is not counted.
	 *
	 * @param int $aid
	 * @return int[]
	 */
	protected function loadChildAids($aid) {
		$query = array_merge(array('parent_aid' => $aid, 'type' => 'account'), $this->overlapQuery);
		$cursor = $this->collection->query($query)->cursor()->sort(array('from' => -1));
		$children = array();
		$seen = array();
		foreach ($cursor as $revision) {
			$childAid = intval($revision['aid']);
			if ($childAid === $aid || isset($seen[$childAid])) {
				continue;
			}
			$seen[$childAid] = true;
			// a child may have several revisions in the cycle; confirm the end-of-cycle one still points here
			$state = $this->getAccountState($childAid);
			if ($state !== false && $state['parent_aid'] === $aid) {
				$children[] = $childAid;
			}
		}
		return $children;
	}

	/**
	 * Parent aid of an account (null for a root or an account not active in the cycle).
	 *
	 * @param int $aid
	 * @return int|null
	 */
	public function getParentAid($aid) {
		$state = $this->getAccountState($aid);
		return $state === false ? null : $state['parent_aid'];
	}

	/**
	 * Coerce a stored value to a strict boolean (bool/int/string representations).
	 */
	protected function resolveBool($data, $field, $default) {
		if (!array_key_exists($field, $data) || is_null($data[$field])) {
			return $default;
		}
		return Billrun_Util::toBool($data[$field], $default);
	}

}
