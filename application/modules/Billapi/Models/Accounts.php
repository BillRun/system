<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billapi accounts model for subscribers entity
 *
 * @package  Billapi
 * @since    5.3
 */
class Models_Accounts extends Models_Entity {

	public $invoicing_day = null;

	protected function init($params) {
		parent::init($params);
		$this->update['type'] = 'account';
		if(Billrun_Factory::config()->isMultiDayCycle()) {
			$this->invoicing_day = $this->getInvoicingDay();
		}
		Billrun_Utils_Mongo::convertQueryMongodloidDates($this->update);
		$this->verifyAllowances();
		$this->verifyServices();
		$this->verifyOverrides();
		$this->verifyHierarchy();
	}

	public function get() {
		$this->query['type'] = 'account';
		return parent::get();
	}

	/**
	 * method to add entity custom fields values from request
	 * 
	 * @param array $fields array of field settings
	 */
	protected function getCustomFields($update = array()) {
		$customFields = parent::getCustomFields();
		$accountFields = Billrun_Factory::config()->getConfigValue($this->collectionName . ".account.fields", array());
		return array_merge($accountFields, $customFields);
	}
	
	public function getCustomFieldsPath() {
		return $this->collectionName . ".account.fields";
	}
	
	/**
	 * Return the key field
	 * 
	 * @return String
	 */
	protected function getKeyField() {
		return 'aid';
	}

	/**
	 * Validate the customer-hierarchy fields (parent_aid / issues_invoice / is_payer) before save.
	 *
	 * Rules (BRCD-785 / BRCD-2824 / BRCD-2827):
	 *  - issues_invoice=false implies is_payer=false.
	 *  - a root (no parent_aid) must issue invoices and be a payer; on disconnect both are auto-set true.
	 *  - parent_aid must reference an existing account, must not be the account itself, must not create a
	 *    cycle, and the resulting tree depth must not exceed the configured maximum (default 4 levels).
	 *
	 * Only runs when one of the hierarchy fields is part of the update, so ordinary account updates are
	 * unaffected.
	 */
	protected function verifyHierarchy() {
		$touchesHierarchy = array_key_exists('parent_aid', $this->update)
			|| array_key_exists('issues_invoice', $this->update)
			|| array_key_exists('is_payer', $this->update);
		if (!$touchesHierarchy) {
			return true;
		}

		// This save may introduce/remove hierarchy in the current process; invalidate the in-use memo so a
		// subsequent getBillable()/aggregation re-probes the DB rather than trusting a stale "flat" result.
		Billrun_Cycle_Hierarchy::resetInUseCache();

		// Normalize the values being written so they are stored with their proper types.
		if (array_key_exists('issues_invoice', $this->update)) {
			$this->update['issues_invoice'] = $this->toHierarchyBool($this->update['issues_invoice'], true);
		}
		if (array_key_exists('is_payer', $this->update)) {
			$this->update['is_payer'] = $this->toHierarchyBool($this->update['is_payer'], true);
		}
		if (array_key_exists('parent_aid', $this->update)) {
			if (is_null($this->update['parent_aid']) || $this->update['parent_aid'] === '') {
				unset($this->update['parent_aid']); // detaching from the parent
			} else {
				$this->update['parent_aid'] = intval($this->update['parent_aid']);
			}
		}

		$aid = isset($this->update['aid']) ? intval($this->update['aid'])
			: (!empty($this->before) ? intval(Billrun_Util::getIn($this->before, 'aid', 0)) : 0);
		$aid = $aid ?: null;

		$parentAid = $this->getEffectiveHierarchyValue('parent_aid', null);
		$parentAid = (is_null($parentAid) || $parentAid === '') ? null : intval($parentAid);

		$issuesInvoice = $this->toHierarchyBool($this->getEffectiveHierarchyValue('issues_invoice', true), true);
		$isPayer = $this->toHierarchyBool($this->getEffectiveHierarchyValue('is_payer', true), true);

		// Root account: must issue invoices and be a payer. On disconnect auto-set both (BRCD-2824).
		if (is_null($parentAid)) {
			if ($issuesInvoice !== true || $isPayer !== true) {
				$this->update['issues_invoice'] = true;
				$this->update['is_payer'] = true;
				$issuesInvoice = true;
				$isPayer = true;
			}
		}

		// A non-invoicing account cannot be a payer.
		if ($issuesInvoice === false && $isPayer !== false) {
			throw new Billrun_Exceptions_Api(0, array(), "A non-invoicing account (issues_invoice=false) must also have is_payer=false");
		}

		if (is_null($parentAid)) {
			return true;
		}

		if (!is_null($aid) && $parentAid === $aid) {
			throw new Billrun_Exceptions_Api(0, array(), "Account {$aid} cannot be its own parent");
		}

		// Parent must exist; the chain must not cycle; the resulting tree depth must not exceed the limit.
		$ancestorLevels = $this->getAncestorLevels($parentAid, $aid);
		$descendantLevels = is_null($aid) ? 0 : $this->getSubtreeDepthBelow($aid);
		$resultingDepth = $ancestorLevels + 1 + $descendantLevels;
		$maxDepth = $this->getMaxHierarchyDepth();
		if ($resultingDepth > $maxDepth) {
			throw new Billrun_Exceptions_Api(0, array(), "Customer hierarchy depth ({$resultingDepth}) exceeds the maximum of {$maxDepth} levels");
		}

		return true;
	}

	/**
	 * Effective value of a hierarchy field: the incoming update wins, otherwise the current revision,
	 * otherwise the provided default.
	 */
	protected function getEffectiveHierarchyValue($field, $default) {
		if (array_key_exists($field, $this->update)) {
			return $this->update[$field];
		}
		if (!empty($this->before)) {
			return Billrun_Util::getIn($this->before, $field, $default);
		}
		return $default;
	}

	/**
	 * Coerce a request/stored value to a strict boolean (handles bool, int and string representations).
	 */
	protected function toHierarchyBool($value, $default) {
		return Billrun_Util::toBool($value, $default);
	}

	protected function getMaxHierarchyDepth() {
		return Billrun_Cycle_Hierarchy::getMaxDepth();
	}

	/**
	 * Latest (most recent 'from') account revision for an aid, or null when none exists.
	 */
	protected function getAccountRevision($aid) {
		$revision = $this->collection->query(array('aid' => intval($aid), 'type' => 'account'))
			->cursor()->sort(array('from' => -1))->limit(1)->current();
		if (!$revision || $revision->isEmpty()) {
			return null;
		}
		return $revision->getRawData();
	}

	/**
	 * Number of ancestor levels from $parentAid up to the root (inclusive of $parentAid).
	 * Throws when the immediate parent does not exist or a cycle is detected.
	 */
	protected function getAncestorLevels($parentAid, $editedAid) {
		$levels = 0;
		$visited = array();
		if (!is_null($editedAid)) {
			$visited[intval($editedAid)] = true;
		}
		$maxDepth = $this->getMaxHierarchyDepth();
		$currentAid = intval($parentAid);
		while (!is_null($currentAid)) {
			if (isset($visited[$currentAid])) {
				throw new Billrun_Exceptions_Api(0, array(), "Customer hierarchy cycle detected at account {$currentAid}");
			}
			$visited[$currentAid] = true;
			$revision = $this->getAccountRevision($currentAid);
			if (is_null($revision)) {
				// Reject any dangling reference in the chain (immediate or higher up) rather than silently
				// treating it as a root, which could let a deeper-than-allowed tree pass validation.
				throw new Billrun_Exceptions_Api(0, array(), "Parent account {$currentAid} does not exist");
			}
			$levels++;
			if ($levels > $maxDepth) {
				// Ancestors alone already exceed the limit; the resulting tree is too deep. (Cycles are
				// caught above by the visited-set check.)
				throw new Billrun_Exceptions_Api(0, array(), "Customer hierarchy depth exceeds the maximum of {$maxDepth} levels");
			}
			$nextParent = isset($revision['parent_aid']) && $revision['parent_aid'] !== '' && !is_null($revision['parent_aid'])
				? intval($revision['parent_aid'])
				: null;
			$currentAid = $nextParent;
		}
		return $levels;
	}

	/**
	 * Maximum number of descendant levels below $aid (0 when it has no children).
	 */
	protected function getSubtreeDepthBelow($aid, $depth = 0) {
		if ($depth > $this->getMaxHierarchyDepth()) {
			return 0; // safety guard against corrupted/looping data
		}
		$max = 0;
		foreach ($this->getChildAids($aid) as $childAid) {
			$below = 1 + $this->getSubtreeDepthBelow($childAid, $depth + 1);
			if ($below > $max) {
				$max = $below;
			}
		}
		return $max;
	}

	/**
	 * Distinct child aids whose latest revision points to $aid as their parent.
	 */
	protected function getChildAids($aid) {
		$childAids = array();
		$cursor = $this->collection->query(array('parent_aid' => intval($aid), 'type' => 'account'))->cursor();
		foreach ($cursor as $revision) {
			$childAid = intval($revision['aid']);
			if ($childAid === intval($aid) || isset($childAids[$childAid])) {
				continue;
			}
			// confirm the child's latest revision still points to this parent (handles close-and-new re-parenting)
			$latest = $this->getAccountRevision($childAid);
			if ($latest && isset($latest['parent_aid']) && intval($latest['parent_aid']) === intval($aid)) {
				$childAids[$childAid] = true;
			}
		}
		return array_keys($childAids);
	}

	/**
	 * validates that the allowances added to the account not added to other account
	 */
	protected function verifyAllowances() {
		$allowances = isset($this->update['allowances']) ? $this->update['allowances'] : [];
		if (empty($allowances)) {
			return true;
		}
		$sids = [];
		foreach ($allowances as $allowance) {
			$sid = $allowance['sid'];
			if (floatval($allowance['allowance']) <= 0) {
				throw new Billrun_Exceptions_Api(0, array(), "Allowance value for SID {$sid} must be a positive number greater than 0.");
			}
			if (in_array($sid, $sids)) {
				throw new Billrun_Exceptions_Api(0, array(), "Subscriber ID {$sid} could have only one allowance");
			}
			$sids[] = $sid;
		}
		$query = ["allowances.sid" => ["\$in" => $sids]];
		if (!empty($this->query['_id'])) {
			$query["_id"] = ["\$ne" => $this->query['_id']];
		} else if (!empty($this->update['aid'])) {
			$query["aid"] = ["\$ne" => $this->update['aid']];
		}

		$account = new Billrun_Account_Db();
		$account->loadAccountForQuery($query);
		if (!$account->getCustomerData()->isEmpty()) {
			$account_sids = array_reduce($account->allowances, function($acc, $allowance) {
				$acc[] = $allowance['sid'];
				return $acc;
			}, []);
			$sid_duplicate = implode(" ,", array_intersect($account_sids, $sids));
			throw new Billrun_Exceptions_Api(0, array(), "Allowances for subscriber IDs {$sid_duplicate} belong to another account.");
		}
		return true;
	}
	
	public function getInvoicingDay () {
		return !empty($this->originalUpdate['invoicing_day']) ? $this->originalUpdate['invoicing_day'] : Billrun_Factory::config()->getConfigChargingDay();
	}

	/**
	 * Verify overrides plan \ services are correct before update is applied to the customer
	 */
	protected function verifyOverrides() {
		if (!empty($this->update['overrides'])) {
			$overrides = Billrun_Util::getIn($this->update, 'overrides', []);
			foreach ($overrides as $override) {
				$priceIntervals = Billrun_Util::getIn($override, ['value','price'], []);
				$type = Billrun_Util::getIn($override, 'type', 'item');
				$key = Billrun_Util::getIn($override, 'key', '');
				foreach ($priceIntervals as $price) {
					if (!isset($price['from']) || $price['from'] === '' || 
						!isset($price['to']) || $price['to'] === '') {
						throw new Billrun_Exceptions_Api(0, array(), "Override {$type} {$key} missing cycles parameters");
					}
					if (!isset($price['price']) || $price['price'] === '') {
						throw new Billrun_Exceptions_Api(0, array(), "Override {$type} {$key} missing price parameter");
					}
				}
			}
		}
	}

	/**
	 * Verify services are correct before update is applied to the subscription
	 * and makes sure it matches his play
	 */
	protected function verifyServices() {
		$services_sources = array();
		if (!empty($this->update['services'])) {
			$services_sources[] = &$this->update['services'];
		}
		if (!empty($this->queryOptions['$push']['services']['$each'])){
			$services_sources[] = &$this->queryOptions['$push']['services']['$each'];
		}
		
		if (empty($services_sources)) {
			return FALSE;
		}
		foreach ($services_sources as &$services_source) {	
			foreach ($services_source as $key => &$service) {
				if (gettype($service) == 'string') {
					$service = array('name' => $service);
				}
				if (gettype($service['from']) == 'string') {
					$service['from'] = new Mongodloid_Date(strtotime($service['from']));
				}
				if (empty($this->before)) { // this is new subscriber
					$service['from'] = isset($service['from']) && $service['from'] >= $this->update['from'] ? $service['from'] : $this->update['from'];
				}
				if (!empty($service['to']) && gettype($service['to']) == 'string') {
					$service['to'] = new Mongodloid_Date(strtotime($service['to']));
				}
				// handle custom period service or limited cycles service
				$serviceTime = $service['from']->sec ?? time();
				$serviceRate = new Billrun_Service(array('name' => $service['name'], 'time' => $serviceTime));
				// if service not found, throw exception
				if (empty($serviceRate) || empty($serviceRate->get('_id'))) {
					$searchDate = !empty($serviceTime) ? date('Y-m-d H:i:s', $serviceTime) : "";
					throw new Billrun_Exceptions_Api(66601, array(), "Service {$service['name']} ({$searchDate}) not found");
				}
				if (!empty($servicePeriod = @$serviceRate->get('balance_period')) && $servicePeriod !== "default") {
					$service['to'] = new Mongodloid_Date(strtotime($servicePeriod, $service['from']->sec));
				} else {
					// Handle limited cycle services
					$serviceAvailableCycles = $serviceRate->getServiceCyclesCount();
					if ($serviceAvailableCycles !== Billrun_Service::UNLIMITED_VALUE) {
						$vDate = date(Billrun_Base::base_datetimeformat, $service['from']->sec);
						$to = strtotime('+' . $serviceAvailableCycles . ' months', Billrun_Billingcycle::getBillrunStartTimeByDate($vDate));
						$service['to'] = new Mongodloid_Date($to);
					}
				}
				if (empty($service['to'])) {
					$service['to'] =  new Mongodloid_Date(strtotime('+149 years'));
				}
				if (!isset($service['service_id'])) {
					$service['service_id'] = hexdec(uniqid());
				}

				if (!isset($service['creation_time'])) {
					$service['creation_time'] = new Mongodloid_Date();
				}
				
			}
		}
	}
}
