<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2020 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */
class Portal_Actions_Settings extends Portal_Actions {

	const ALLOWED_PORTAL_CONFIGURATION_FIELDS = [
		"logo_url",
		"color_main",
		"color_main_highlight",
		"color_second",
		"color_second_highlight",
		"color_text",
		"color_text_highlight",
	];

	public function get($params = []) {
		$categories = $params['categories'] ?? [];

		if (empty($categories)) {
			throw new Portal_Exception('missing_parameter', '', 'Missing parameter: "categories"');
		}
		$allow_categories = $this->params['allow_categories'] ? array_map('trim', explode(',', $this->params['allow_categories'])) : [];
		foreach ($categories as $category) {
			// portal always allowed
			if ($category === 'portal') {
				$portal_settings = array_filter($this->params, function ($key) {
					return in_array($key, self::ALLOWED_PORTAL_CONFIGURATION_FIELDS);
				}, ARRAY_FILTER_USE_KEY
				);
				$res[$category] = $portal_settings;
			} else {
				//check if category allow
				if (in_array($category, $allow_categories)) {
					$res[$category] = Billrun_Factory::config()->getConfigValue($category);
				} else {
					throw new Portal_Exception('permission_denied', '', 'Permission denied to get category : ' . $category);
				}
			}
		}
		return $res;
	}

	/**
	 * Authorize the request.
	 * 
	 * @param  string $action
	 * @param  array $params
	 * @return boolean
	 */
	protected function authorize($action, &$params = []) {
		return true;
	}

}
