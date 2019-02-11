<?php

/**
 * @package	Billing
 * @copyright	Copyright (C) 2012-2017 BillRun Technologies Ltd. All rights reserved.
 * @license	GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Plugin to keep API's backward compatibles
 *
 * @package  Application
 * @subpackage Plugins
 * @since    5.9
 */
class billapiTranslationPlugin extends Billrun_Plugin_BillrunPluginBase {
	
	protected $loadedConfigs = [];
	
	protected $configs = [];
	
	public function shouldTranslate($request) {
		return Billrun_Util::getIn($request, 'translate', false);
	}


	public function beforeBillApiRunOperation($collection, $action, &$request) {
		if (!$this->shouldTranslate($request)) {
			return;
		}
	}
	
	public function afterBillApi($collection, $action, $request, &$output) {
		$request = $request->getRequest();
		if (!$this->shouldTranslate($request)) {
			return;
		}
		
		if ($collection == 'plans') {
			$this->enrichPlansResponse($output);
		}
	}
	
	protected function translatePlanGetRequest(&$request) {	
	}
	
	protected function enrichPlansResponse(&$output) {
		$this->loadBillApiConfig('services');
		foreach ($output->details as &$plan) {
			$services = $this->getPlanServices($plan);
			$billableOptions = [];
			$nonBillableOptions = [];
			foreach ($services as $service) {
				$serviceName = $service['name'];
				$billable = Billrun_Util::getIn($service, 'billable', true);
				if ($billable) {
					$billableOptions[$serviceName] = $service;
				} else {
					$nonBillableOptions[$serviceName] = $service;
				}
			}
			$plan['options'] = $billableOptions;
			$plan['not_billable_options'] = $nonBillableOptions;
		}
	}
	
	protected function getPlanServices($plan) {
		$servicesNames = Billrun_Util::getIn($plan, 'include.services', []);
		$collection = 'services';
		$action = 'uniqueget';
		$query = [
			'name' => ['$regex' => implode('|', $servicesNames) ],
		];
		$params = [
			'request' => [
				'collection' => $collection,
				'action' => $action,
				'query' => json_encode($query),
			],
			'settings' => $this->getActionConfig($collection, $action),
		];
		$action = new Models_Action_Uniqueget($params);
		return $action->execute();
	}
	
	protected function loadBillApiConfig($collection) {
		if (in_array($collection, $this->loadedConfigs)) {
			return;
		}
		Billrun_Factory::config()->addConfig(APPLICATION_PATH . '/conf/modules/billapi/' . $collection . '.ini');
		$this->loadedConfigs[] = $collection;
	}
	
	protected function getActionConfig($collection, $action) {
		$configVar = 'billapi.' . $collection . '.' . $action;
		if (!isset($this->configs[$configVar])) {
			$this->configs[$configVar] = Billrun_Factory::config()->getConfigValue($configVar, []);
		}
		return $this->configs[$configVar];
	}
}
