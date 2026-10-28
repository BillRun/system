<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billapi unique get operation
 * Retrieve list of entities while the key or name field is unique
 * This is accounts unique get
 *
 * @package  Billapi
 * @since    5.3
 */
class Models_Action_Get_Accounts extends Models_Action_Get {

	protected function runQuery() {
		$this->query['type'] = 'account';
		return parent::runQuery();
	}

	protected function getCollectionName() {
		return 'subscribers';
	}
	
	protected function getCustomFieldsKey() {
		return $this->getCollectionName() . ".account";
	}
	
	protected function getDateFields() {
		$fields = parent::getDateFields();
		$fields[] = 'payment_gateway.active.generate_token_time';
		$fields[] = 'in_collection_from';
		return array_unique($fields);
	}

	/**
	 * BRCD-785: enrich each returned account with its hierarchy_ancestors array - ordered list of
	 * ancestor aids from root -> immediate parent (the account's own aid is NOT included). Computed by
	 * walking parent_aid via each account's latest revision; bounded by customer.hierarchy.max_depth.
	 */
	protected function processResults(Mongodloid_Cursor $cursor) {
		$records = parent::processResults($cursor);
		foreach ($records as &$record) {
			if (isset($record['aid'])) {
				$record['hierarchy_ancestors'] = $this->buildHierarchyAncestors(intval($record['aid']));
			}
		}
		return $records;
	}

	/**
	 * Ordered ancestor aids for an account, root -> immediate parent (excludes the account itself).
	 * Reads the latest revision of each account via parent_aid; returns [] for a root or unknown aid.
	 *
	 * @param int $aid
	 * @return int[]
	 */
	protected function buildHierarchyAncestors($aid) {
		$ancestors = array();
		$visited = array(intval($aid) => true);
		$maxDepth = Billrun_Cycle_Hierarchy::getMaxDepth();
		$current = intval($aid);
		while (count($ancestors) <= $maxDepth) {
			$revision = $this->collectionHandler
				->query(array('aid' => $current, 'type' => 'account'))
				->cursor()->sort(array('from' => -1))->limit(1)->current();
			if (!$revision || $revision->isEmpty()) {
				break;
			}
			$parent = isset($revision['parent_aid']) && $revision['parent_aid'] !== '' && !is_null($revision['parent_aid'])
				? intval($revision['parent_aid'])
				: null;
			if (is_null($parent) || isset($visited[$parent])) {
				break;
			}
			$visited[$parent] = true;
			array_unshift($ancestors, $parent);
			$current = $parent;
		}
		return $ancestors;
	}

}
