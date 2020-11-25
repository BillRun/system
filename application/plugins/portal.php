<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2020 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

 require_once APPLICATION_PATH . '/application/helpers/Portal/Exception.php';

/**
 * Plugin to handle customer portal API's
 *
 * @package  Application
 * @subpackage Plugins
 * @since    5.14
 */
class portalPlugin extends Billrun_Plugin_BillrunPluginBase {
	/**
	 * plugin name
	 *
	 * @var string
	 */
	protected $name = 'portal';

	/**
	 * customer portal config
	 * 
	 * @var string
	 */
	protected $config;
	
	/**
	 * setup plugin input
	 * 
	 * @return void
	 */
	public function getConfigurationDefinitions() {
		return [
				[
					'type' => 'string',
					'field_name' => 'authentication_field',
					'title' => 'Authentication Field',
					'select_list' => true,
					'select_options' => implode(',', $this->getAccountUniqueFields()),
					'editable' => true,
					'display' => true,
					'nullable' => false,
				],
			];
	}
	
	/**
	 * get account field marked as unique and searchable
	 *
	 * @return array
	 */
	protected function getAccountUniqueFields() {
		$customFields = array_merge(
			Billrun_Factory::config()->getConfigValue('subscribers.fields', []),
			Billrun_Factory::config()->getConfigValue('subscribers.account.fields', [])
		);
		$ret = [];

		foreach ($customFields as $customField) {
			$isUnique = !empty($customField['unique']);
			$isSearchable = !empty($customField['system']) || !empty($customField['searchable']);
			if ($isUnique && $isSearchable) {
				$ret[] = $customField['field_name'];
			}
		}

		return $ret;
	}

}