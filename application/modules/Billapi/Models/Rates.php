<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billapi balances model for rates entity
 *
 * @package  Billapi
 * @since    5.3
 */
class Models_Rates extends Models_Entity {	
	protected function init($params) {
		parent::init($params);
		
		if (isset($this->update['tariff_category']) && $this->update['tariff_category'] == 'retail') {
			$this->update['add_to_retail'] = true;
		}
                if (isset($this->update['rounding_rules'])){
                    if(!isset($this->update['rounding_rules']['rounding_type'])){
                        throw new Billrun_Exceptions_Api(0, array(), "Rounding rules must have rounding type");
                    }
                    if($this->update['rounding_rules']['rounding_type']!=='None' && !isset($this->update['rounding_rules']['rounding_decimals'])){
                        throw new Billrun_Exceptions_Api(0, array(), "Rounding rules must have rounding decimal");
                    }
                }
	}
	
	/**
	 * method to add entity custom fields values from request
	 * 
	 * @param array $fields array of field settings
	 */
	protected function getCustomFields($update = array()) {
		$customFields = parent::getCustomFields();
		$play = Billrun_Util::getIn($update, 'play', Billrun_Util::getIn($this->before, 'play', ''));
		return Billrun_Utils_Plays::filterCustomFields($customFields, $play);
	}
	
	/**
	 * Returns true if current record does not overlap with existing records in the DB
	 * @param array $data
	 * @param array $ignoreIds
	 * @return boolean
	 */
	protected function duplicateCheck($data, $ignoreIds = array()) {
		// to allow create revisions - if OK can be moved to the parrent
		if ($this->is_import) {
			$duplicate_check_fields = Billrun_Util::getFieldVal($this->config['duplicate_check'], []);
			$query = array();
			foreach ($duplicate_check_fields as $fieldName) {
				$query[$fieldName] = $data[$fieldName];
			}
			$query['from'] = [
				'$lt' => $data['to']
			];
			$query['to'] = [
				'$gt' => $data['from']
			];
			if ($ignoreIds) {
				$query['_id'] = array(
					'$nin' => $ignoreIds,
				);
			}
			return $query ? !$this->collection->query($query)->count() : TRUE;
		}
		return parent::duplicateCheck($data, $ignoreIds);
	}

}
