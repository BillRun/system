<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * This is a prototype for a Realtime response action.
 *
 */
class Billrun_ActionManagers_Realtime_Responder_Realtime_Base extends Billrun_ActionManagers_Realtime_Responder_Base {

	protected $config = array();

	public function __construct(array $options = array()) {
		parent::__construct($options);
		$this->config = $options['config'];
	}

	protected function getResponseFields() {
		return (isset($this->config['response']['fields']) ? $this->config['response']['fields'] : Billrun_Factory::config()->getConfigValue('realtimeevent.responseData.basic', array()));
	}

	public function getResponsApiName() {
		return 'realtime';
	}

}
