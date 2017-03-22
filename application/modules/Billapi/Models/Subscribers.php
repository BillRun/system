<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billapi subscribers model for subscribers entity
 *
 * @package  Billapi
 * @since    5.3
 */
class Models_Subscribers extends Models_Entity {

	protected function init($params) {
		parent::init($params);
		$this->update['type'] = 'subscriber';

		// TODO: move to translators?
		if (empty($this->before)) { // this is new subscriber
			$this->update['plan_activation'] = isset($this->update['from']) ? $this->update['from'] : new MongoDate();
		} else if (isset($this->before['plan_activation']) && isset($this->update['plan']) 
			&& isset($this->before['plan']) && $this->before['plan'] !== $this->update['plan']) { // plan was changed
			$this->update['plan_activation'] = isset($this->update['from']) ? $this->update['from'] : new MongoDate();
		} else { // plan was not changed
			$this->update['plan_activation'] = $this->before['plan_activation'];
		}
		
		//transalte to and from fields
		Billrun_Utils_Mongo::convertQueryMongoDates($this->update);
				
		$this->verifyServices();
		
	}

	public function get() {
		$this->query['type'] = 'subscriber';
		return parent::get();
	}

	/**
	 * method to add entity custom fields values from request
	 * 
	 * @param array $fields array of field settings
	 */
	protected function getCustomFields() {
		$customFields = parent::getCustomFields();
		$accountFields = Billrun_Factory::config()->getConfigValue($this->collectionName . ".subscriber.fields", array());
		return array_merge($accountFields, $customFields);
	}
	
	/**
	 * Verfiy services are corrrect before update is applied tothe subscrition.
	 */
	protected function verifyServices() {
		if(empty($this->update)) {
			return FALSE;
		}
		foreach($this->update['services'] as  &$service) {
			if(gettype($service) =='string') {
				$service = array('name' => $service);
			}
			if (empty($this->before)) { // this is new subscriber
				$service['from'] = isset($service['from']) && $service['from'] >= $this->update['from'] ? $service['from'] : $this->update['from'];				
			} 
			//to  can't be more then the updated 'to' of the subscription
			$service['to'] = isset($service['to']) && $service['to'] <= $this->update['to'] ? $service['to'] : $this->update['to'];
		}
	}
	
		
	/**
	 * Return the key field
	 * 
	 * @return String
	 */
	protected function getKeyField() {
		return 'sid';
	}

}
