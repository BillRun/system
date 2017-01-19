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
		foreach ($currencySymbols as $key => $currencySymbol) {
			if ($simpleArray) {
				$ret[] = array(
					'symbol' => $currencySymbol,
					'code' => $key,
				);
				if (isset($currencyNames[$key])) {
					$ret[count($ret)-1]['name'] = $currencyNames[$key];
				}
			} else {
				$ret[$key] = array(
					'symbol' => $currencySymbol,
					'code' => $key,
				);
				if (isset($currencyNames[$key])) {
					$ret[$key]['name'] = $currencyNames[$key];
				}
			}
		}
		$this->getView()->list = $ret;
	}
	
	protected function getPermissionLevel() {
		return Billrun_Traits_Api_IUserPermissions::PERMISSION_READ;
	}
}
