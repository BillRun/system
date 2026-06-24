<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2026 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Feed-backed customer-hierarchy resolver for external subscribers mode (BRCD-785).
 *
 * In external mode accounts live in a 3rd-party CRM and are never persisted to the local subscribers
 * collection, so the Mongo-backed resolver has nothing to read. The CRM instead returns each invoicing
 * account together with its full subtree in the GBA/GAD feed (see the outgoing-API contract), and this
 * resolver answers the same hierarchy questions from that in-memory feed.
 *
 * It overrides only the two state-source seams of the parent (loadAccountState / loadChildAids); every
 * derived walk (getInvoiceOwner, getRollupDescendants, getSubtreeAids, getPayerAid, ...) and all caching
 * are inherited unchanged, so DB and external modes resolve hierarchy identically for the same tree.
 *
 * @package  Cycle
 * @since    6.0
 */
class Billrun_Cycle_Hierarchy_Feed extends Billrun_Cycle_Hierarchy {

	/**
	 * aid => raw end-of-cycle account revision (carrying parent_aid/issues_invoice/is_payer).
	 * @var array
	 */
	protected $accountData = array();

	/**
	 * parentAid => list of child aids, derived lazily from accountData. Null until first built.
	 * @var array|null
	 */
	protected $childrenIndex = null;

	/**
	 * @param Billrun_DataTypes_CycleTime|Billrun_DataTypes_MongoCycleTime $cycle
	 * @param array $accounts the parsed per-page accounts (aid => ['attributes'=>..., 'subscribers'=>[...]])
	 * @param int|null $maxDepth defaults to config customer.hierarchy.max_depth
	 */
	public function __construct($cycle, $accounts, $maxDepth = null) {
		parent::__construct($cycle, $maxDepth);
		$this->accountData = self::extractAccountData($accounts);
	}

	/**
	 * Build the aid => end-of-cycle account-revision map from a parsed accounts page. The account-level
	 * (sid=0) revision is where parseToAccounts merges the hierarchy passthrough fields; when several
	 * revisions overlap the cycle we keep the latest by 'from' to mirror the DB resolver's end-of-cycle
	 * snapshot.
	 *
	 * @param array $accounts
	 * @return array aid => raw revision
	 */
	public static function extractAccountData($accounts) {
		$data = array();
		foreach ($accounts as $aid => $accountData) {
			$revisions = Billrun_Util::getIn($accountData, array('subscribers', 0), array());
			if (empty($revisions)) {
				continue;
			}
			$chosen = null;
			foreach ($revisions as $revision) {
				if (is_null($chosen) || self::revisionFrom($revision) >= self::revisionFrom($chosen)) {
					$chosen = $revision;
				}
			}
			$data[intval($aid)] = $chosen;
		}
		return $data;
	}

	/**
	 * Normalize a revision 'from' to unix seconds for end-of-cycle selection. Handles Mongo date objects,
	 * numeric values and absent fields.
	 *
	 * @param array $revision
	 * @return int
	 */
	protected static function revisionFrom($revision) {
		if (!isset($revision['from'])) {
			return 0;
		}
		$from = $revision['from'];
		if (is_object($from) && isset($from->sec)) {
			return intval($from->sec);
		}
		return is_numeric($from) ? intval($from) : 0;
	}

	/**
	 * @see Billrun_Cycle_Hierarchy::loadAccountState()
	 */
	protected function loadAccountState($aid) {
		$aid = intval($aid);
		if (!array_key_exists($aid, $this->accountData)) {
			return false; // not on this page's feed -> treated as inactive in the cycle
		}
		return $this->normalizeState($aid, $this->accountData[$aid]);
	}

	/**
	 * @see Billrun_Cycle_Hierarchy::loadChildAids()
	 */
	protected function loadChildAids($aid) {
		$this->buildChildrenIndex();
		$aid = intval($aid);
		return isset($this->childrenIndex[$aid]) ? $this->childrenIndex[$aid] : array();
	}

	/**
	 * Build parentAid => [childAids] from the feed. Each child is bucketed under its end-of-cycle
	 * parent_aid (resolved through the cached getAccountState), so a child reparented mid-cycle is
	 * attributed to its latest parent - matching the DB resolver's re-check.
	 */
	protected function buildChildrenIndex() {
		if (!is_null($this->childrenIndex)) {
			return;
		}
		$this->childrenIndex = array();
		foreach (array_keys($this->accountData) as $childAid) {
			$state = $this->getAccountState($childAid);
			if ($state === false || is_null($state['parent_aid'])) {
				continue;
			}
			$this->childrenIndex[$state['parent_aid']][] = intval($childAid);
		}
	}

}
