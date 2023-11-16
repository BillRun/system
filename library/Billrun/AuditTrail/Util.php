<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Util class for the Audit Trail
 *
 * @package  Util
 * @since    5.5
 */
class Billrun_AuditTrail_Util {

	/**
	 * Log an audit trail event (by adding it to 'log' collection with source='audit')
	 * 
	 * @param string $type
	 * @param string $key
	 * @param string $collection
	 * @param array $old
	 * @param array $new
	 * @param array $additionalParams
	 * @return boolean true on success, false otherwise
	 */
	public static function trackChanges($type = '', $key = '', $collection = '', $old = null, $new = null, array $additionalParams = array()) {
		Billrun_Factory::log("Track changes in audit trail", Zend_Log::DEBUG);
		try {
			$user = Billrun_Factory::user();
			if ($user) {
				$trackUser = array(
					'_id' => $user->getMongoId()->getMongoID(),
					'name' => $user->getUsername(),
					'api' => false,
				);
			} else { // in case 3rd party API update with token
				$oauth2 = Billrun_Factory::oauth2();
				$access_token = $oauth2->getAccessTokenData(OAuth2\Request::createFromGlobals());
				
				$trackUser = array(
					'_id' => $access_token['_id'] ?? null,
					'name' => $access_token['client_id'] ?? '_3RD_PARTY_TOKEN_',
					'api' => true
				);
			}
			$basicLogEntry = array(
				'source' => 'audit',
				'collection' => $collection,
				'type' => $type,
				'urt' => new Mongodloid_Date(),
				'user' => $trackUser,
				'old' => $old,
				'new' => $new,
				'key' => $key,
			);
			
			$logEntry = array_merge($basicLogEntry, $additionalParams);
			$logEntry['stamp'] = Billrun_Util::generateArrayStamp($logEntry);
			Billrun_Factory::db()->auditCollection()->save(new Mongodloid_Entity($logEntry));
			Billrun_Factory::dispatcher()->trigger('trackChanges', array($old, $new, $collection, $type, $trackUser));
			return true;
		} catch (Exception $ex) {
			Billrun_Factory::log('Failed on insert to audit trail. ' . $ex->getCode() . ': ' . $ex->getMessage(), Zend_Log::ERR);
		}
		return false;
	}
}
