<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2015 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */
abstract class Billrun_ActionManagers_Statistics_Action extends Billrun_ActionManagers_APIAction {
	protected $collection = null;
	
	public function __construct($params) {
		$this->collection = Billrun_Factory::db()->statisticsCollection();
		parent::__construct($params);
	}
	
	public abstract function parse($request);
	public abstract function execute();
}