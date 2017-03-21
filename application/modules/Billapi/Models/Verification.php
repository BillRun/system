<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billapi verification trait model for 
 *
 * @package  Billapi
 * @since    5.3
 */
trait Models_Verification {

	/**
	 * Returns the translated (validated) request
	 * @param array $query the query parameter
	 * @param array $data the update parameter
	 * 
	 * @return array
	 * 
	 * @throws Billrun_Exceptions_Api
	 * @throws Billrun_Exceptions_InvalidFields
	 */
	protected function validateRequest($query, $data, $action, $config, $error, $forceNotEmpty = true) {
		$options = array();
		foreach (array('query_parameters' => $query, 'update_parameters' => $data) as $type => $params) {
			$options['fields'] = array();
			$translated[$type] = array();
			$configParams = Billrun_Util::getFieldVal($config[$type], array());
			foreach ($configParams as $param) {
				$name = $this->getParamName($param, $params);
				$isGenerated = (isset($param['generated']) && $param['generated']);
				if (!isset($params[$name]) || $params[$name] === "") {
					if (isset($param['mandatory']) && $param['mandatory'] && !$isGenerated) {
						throw new Billrun_Exceptions_Api($error, array(), 'Mandatory ' . str_replace('_parameters', '', $type) . ' parameter ' . $name . ' missing');
					}
					if (!$isGenerated) {
						continue;
					}
				}
				$options['fields'][] = array(
					'name' => $name,
					'type' => $param['type'],
					'preConversions' => isset($param['pre_conversion']) ? $param['pre_conversion'] : [],
					'postConversions' => isset($param['post_conversion']) ? $param['post_conversion'] : [],
					'options' => [],
				);
				if (!$isGenerated) {
					$knownParams[$name] = $params[$name];
				} else { // on generate field the value will be automatically generate
					$knownParams[$name] = null;
				}
				unset($params[$name]);
			}
			if ($options['fields']) {
				$translatorModel = new Api_TranslatorModel($options);
				$ret = $translatorModel->translate($knownParams);
				$translated[$type] = $ret['data'];
//				Billrun_Factory::log("Translated result: " . print_r($ret, 1));
				if (!$ret['success']) {
					throw new Billrun_Exceptions_InvalidFields($translated[$type]);
				}
			}
			if (!Billrun_Util::getFieldVal($config[$action]['restrict_query'], 1) && $params) {
				$translated[$type] = array_merge($translated[$type], $params);
			}
		}
		if ($forceNotEmpty) {
			$this->verifyTranslated($translated);
		}
		return array($translated['query_parameters'], $translated['update_parameters']);
	}
	
	protected function getParamName($param, $params) {
		$paramNameToFind = $param['name'] . '.';
		$paramNameToFindLength = strlen($paramNameToFind);
		foreach (array_keys($params) as $key) {
			if ((substr($key, 0, $paramNameToFindLength) === $paramNameToFind)) {
				return $key;
			}
		}
		return $param['name'];
	}

	/**
	 * Verify the translated query & update
	 * @param array $translated
	 */
	protected function verifyTranslated($translated) {
		if (!$translated['query_parameters'] && !$translated['update_parameters']) {
			throw new Billrun_Exceptions_Api($this->errorBase + 2, array(), 'No query/update was found or entity not supported');
		}
	}

}
