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
	
        const ALLOW_CATEGORIES_WHITE_LIST = [
            "usage_types",
            "pricing.currency",
            "subscribers.account.fields",
            "subscribers.subscriber.fields",
            "billrun.charging_day"
        ];
        
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
					'select_options' => implode(',', $this->getAccountUniqueFields()), //TODO: add support for subscriber level login
					'editable' => true,
					'display' => true,
					'nullable' => false,
				],
				[
					'type' => 'string',
					'field_name' => 'token_secret',
					'title' => 'Token Secret (Salt)',
					'mandatpry' => true,
					'editable' => true,
					'display' => true,
					'nullable' => false,
				],
				[
					'type' => 'integer',
					'field_name' => 'usages_months_limit',
					'title' => 'Display only usages from (Months ago)', //need to be only for usages?? 
					'mandatpry' => true,
					'editable' => true,
					'display' => true,
					'nullable' => false,
					'default_value' => 24                             
					//todo:: need to allow only positive number (FE task)
					//'min' => 0
				],
				[
					'type' => 'string',
					'field_name' => 'allow_categories',
					'title' => 'Permitted settings values',
					'select_options' => implode(',', self::ALLOW_CATEGORIES_WHITE_LIST),
					'select_list' => true,
					'editable' => true,
					'display' => true,
					'nullable' => false,
					"multiple" => true,
					'default_value' => implode(',', self::ALLOW_CATEGORIES_WHITE_LIST),
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