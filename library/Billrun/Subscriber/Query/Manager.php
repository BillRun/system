<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Manager of the subscriber queries.
 *
 * @package  Billing
 * @since    4
 * @todo Should move to a generic class of generic factory.
 */
class Billrun_Subscriber_Query_Manager {

	/**
	 * Array to hold all the query handlers.
	 * @var Billrun_Subscriber_Query_Interface 
	 */
	static $queryHandlers = array();

	/**
	 * Handle input parameters.
	 * @param array $params - Array of input parameters to handle.
	 * @return query - Query to run to get a subscriber.
	 */
	public static function handle($params) {
		$result = false;
		if (!isset($params['time'])) {
			return FALSE;
		}
		// Go through the handlers.
		foreach (self::$queryHandlers as $handler) {

			// Get the query.
			$result = $handler->getQuery($params);
			if ($result !== false) {
				break;
			}
		}
		if ($result) {
			$result = array_merge($result, Billrun_Utils_Mongo::getDateBoundQuery(strtotime($params['time'])), array('type' => 'subscriber'));
		}

		return $result;
	}

	/**
	 * Register a query handler to the list.
	 * @param Billrun_Subscriber_Query_Interface $queryHandler - Handler to register.
	 * @return boolean true if successful.
	 */
	public static function register($queryHandler) {
		// Validate the input
		if (!($queryHandler instanceof Billrun_Subscriber_Query_Interface)) {
			Billrun_Factory::log("Non query interface tried to register to query manager.", Zend_Log::DEBUG);
			return false;
		}

		self::$queryHandlers[] = $queryHandler;

		return true;
	}

}
