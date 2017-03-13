<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing locale controller class
 * Used for retrieving locale information
 * 
 * @package  Controller
 * @since    5.3
 */
class LocaleController extends ApiController {

	use Billrun_Traits_Api_UserPermissions;

	public function indexAction() {
		$this->allowed();
		$locale = $this->getRequest()->getRequest('locale', null);
		if (!is_string($locale) && !is_null($locale)) {
			return;
		}
		$currencyNames = Zend_Locale::getTranslationList('nametocurrency', $locale);
		$currencySymbols = Zend_Locale::getTranslationList('currencysymbol');
		$simpleArray = (bool) $this->getRequest()->getRequest('simpleArray', false);
		$ret = array();
		foreach ($currencyNames as $key => $name) {
			if ($simpleArray) {
				$ret[] = array(
					'code' => $key,
					'name' => $name,
				);
				if (isset($currencySymbols[$key])) {
					$ret[count($ret) - 1]['symbol'] = $currencySymbols[$key];
				}
			} else {
				$ret[$key] = array(
					'code' => $key,
					'name' => $name,
				);
				if (isset($currencySymbols[$key])) {
					$ret[$key]['symbol'] = $currencySymbols[$key];
				}
			}
		}

		$output = array(
			'status' => !empty($ret) ? 1 : 0,
			'desc' => !empty($ret) ? 'success' : 'error',
			'details' => empty($ret) ? array() : $ret,
		);

		$this->getView()->list = $output;
	}

	protected function getPermissionLevel() {
		return Billrun_Traits_Api_IUserPermissions::PERMISSION_READ;
	}

}
