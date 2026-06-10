<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2026 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billapi accounts gethierarchy operation (BRCD-785).
 *
 * Returns the full subtree rooted at the given aid, in the same shape as the `attributes.hierarchy`
 * map persisted on a billrun document:
 *
 *   { aid: { name, parent_aid }, ... }
 *
 * The map includes the input aid AND every descendant reachable through parent_aid (without stopping
 * at invoicing-account boundaries - it is the full structural hierarchy under the aid, not just one
 * invoice unit). Bounded by customer.hierarchy.max_depth.
 *
 * @package  Billapi
 * @since    5.27
 */
class Models_Action_Gethierarchy_Accounts extends Models_Action {

	protected function getCollectionName() {
		return 'subscribers';
	}

	public function execute() {
		// $this->query carries the JSON-decoded validated query params (the billapi convention used by
		// every other action). The mandatory aid query_parameter in conf/modules/billapi/accounts.ini
		// has already been enforced by validateRequest by the time we get here.
		$aid = isset($this->query['aid']) ? intval($this->query['aid']) : 0;
		if ($aid <= 0) {
			throw new Billrun_Exceptions_Api(0, array(), "Missing or invalid required parameter: aid");
		}
		$result = array();
		$this->collectSubtree($aid, $result, 0);
		if (empty($result)) {
			throw new Billrun_Exceptions_Api(0, array(), "Account {$aid} does not exist");
		}
		return $result;
	}

	/**
	 * Recursively collect the subtree under $aid into $result, keyed by aid.
	 * Bounded by customer.hierarchy.max_depth so corrupted/cyclic data cannot blow up the walk.
	 */
	protected function collectSubtree($aid, array &$result, $depth) {
		$maxDepth = Billrun_Cycle_Hierarchy::getMaxDepth();
		if ($depth > $maxDepth || isset($result[$aid])) {
			return;
		}
		$revision = $this->loadLatestAccountRevision($aid);
		if (!$revision) {
			return;
		}
		$parent = (isset($revision['parent_aid']) && $revision['parent_aid'] !== '' && !is_null($revision['parent_aid']))
			? intval($revision['parent_aid'])
			: null;
		$result[$aid] = array(
			'_id' => isset($revision['_id']) ? $revision['_id'] : null,
			'name' => $this->getAccountDisplayName($revision, $aid),
			'parent_aid' => $parent,
			'issues_invoice' => Billrun_Util::toBool(isset($revision['issues_invoice']) ? $revision['issues_invoice'] : null, true),
			'is_payer' => Billrun_Util::toBool(isset($revision['is_payer']) ? $revision['is_payer'] : null, true),
		);
		foreach ($this->findChildAids($aid) as $childAid) {
			$this->collectSubtree($childAid, $result, $depth + 1);
		}
	}

	/**
	 * Latest (max from) account revision for $aid as raw array, or null when none exists.
	 */
	protected function loadLatestAccountRevision($aid) {
		$revision = $this->collectionHandler
			->query(array('aid' => intval($aid), 'type' => 'account'))
			->cursor()->sort(array('from' => -1))->limit(1)->current();
		if (!$revision || $revision->isEmpty()) {
			return null;
		}
		return $revision->getRawData();
	}

	/**
	 * Distinct child aids whose latest revision still points to $aid as their parent.
	 */
	protected function findChildAids($aid) {
		$childAids = array();
		$cursor = $this->collectionHandler
			->query(array('parent_aid' => intval($aid), 'type' => 'account'))
			->cursor()->sort(array('from' => -1));
		foreach ($cursor as $candidate) {
			$childAid = intval($candidate['aid']);
			if ($childAid === intval($aid) || isset($childAids[$childAid])) {
				continue;
			}
			// confirm the child's latest revision still points here (handles close-and-new re-parenting)
			$latest = $this->loadLatestAccountRevision($childAid);
			if ($latest && isset($latest['parent_aid']) && intval($latest['parent_aid']) === intval($aid)) {
				$childAids[$childAid] = true;
			}
		}
		return array_keys($childAids);
	}

	/**
	 * Display name for an account record (mirrors the breadcrumb name resolution).
	 */
	protected function getAccountDisplayName(array $record, $aid) {
		foreach (array('company_name', 'fullname') as $field) {
			if (!empty($record[$field])) {
				return $record[$field];
			}
		}
		$first = isset($record['firstname']) ? $record['firstname'] : (isset($record['first_name']) ? $record['first_name'] : '');
		$last = isset($record['lastname']) ? $record['lastname'] : (isset($record['last_name']) ? $record['last_name'] : '');
		$name = trim($first . ' ' . $last);
		return $name !== '' ? $name : ('Account ' . intval($aid));
	}

}
