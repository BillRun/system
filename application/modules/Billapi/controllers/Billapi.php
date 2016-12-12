<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billapi abstract controller for BillRun entities available actions
 *
 * @package  Billapi
 * @since    0.5
 */
abstract class BillapiController extends Yaf_Controller_Abstract {

	/**
	 * The output sent to the view
	 * @var stdClass
	 */
	protected $output;

	/**
	 * The requested collection name
	 * @var string
	 */
	protected $collection;

	/**
	 * The action to perform (create/update/delete/get)
	 * @var string
	 */
	protected $action;

	/**
	 * The base error number of this module
	 * @var int
	 */
	protected $errorBase;

	/**
	 * parameters to be used by the model
	 * 
	 * @var array
	 */
	protected $params = array();

	public function indexAction() {
		$entityModel = $this->getModel();
		$res = $entityModel->{$this->action}();
		$this->output->status = 1;
		$this->output->details = $res;
	}

	public function init() {
		$request = $this->getRequest();
		$this->errorBase = Billrun_Factory::config()->getConfigValue('billapi.error_base', 10400);
		$this->collection = $request->getParam('collection');
		$this->action = strtolower($request->getParam('action'));
		$this->output = new stdClass();
		$this->getView()->output = $this->output;
		Yaf_Loader::getInstance(APPLICATION_PATH . '/application/modules/Billapi')->registerLocalNamespace("Models");
		
		$query = json_decode($request->get('query'), TRUE);
		$update = json_decode($request->get('update'), TRUE);
		list($translatedQuery, $translatedUpdate) = $this->validateRequest($query, $update);
		$this->params['query'] = $translatedQuery;
		$this->params['update'] = $translatedUpdate;

	}

	/**
	 * Get the right model, depending on the requested collection
	 * @return \Models_Entity
	 */
	protected function getModel() {
		$modelPrefix = 'Models_';
		$className = $modelPrefix . ucfirst($this->collection);
		if (!@class_exists($className)) {
			$className = $modelPrefix . 'Entity';
		}
		$this->params['collection'] = $this->collection;
		return new $className($this->params);
	}

	/**
	 * Get the relevant billapi config depending on the requested collection + action
	 * @return array
	 */
	protected function getActionConfig() {
		return Billrun_Factory::config()->getConfigValue('billapi.' . $this->collection . '.' . $this->action, array());
	}

	/**
	 * Returns the translated (validated) request
	 * @param array $query the query parameter
	 * @param array $data the update parameter
	 * @return array
	 * @throws Billrun_Exceptions_Api
	 * @throws Billrun_Exceptions_InvalidFields
	 */
	protected function validateRequest($query, $data) {
		$parametersSettings = $this->getActionConfig();
		$options = array();
		foreach (array('query_parameters' => $query, 'update_parameters' => $data) as $type => $params) {
			$options['fields'] = array();
			$translated[$type] = array();
			foreach (Billrun_Util::getFieldVal($parametersSettings[$type], array()) as $param) {
				$name = $param['name'];
				if (!isset($params[$name])) {
					if (isset($param['mandatory']) && $param['mandatory']) {
						throw new Billrun_Exceptions_Api($this->errorBase + 1, array(), 'Mandatory ' . str_replace('_parameters', '', $type) . ' parameter ' . $name . ' missing');
					}
					continue;
				}
				$options['fields'][] = array(
					'name' => $name,
					'type' => $param['type'],
					'preConversions' => isset($param['pre_conversion']) ? $param['pre_conversion'] : [],
					'postConversions' => isset($param['post_conversion']) ? $param['post_conversion'] : [],
					'options' => [],
				);
				$knownParams[$name] = $params[$name];
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
			if (!Billrun_Util::getFieldVal($parametersSettings['restrict_query'], 1) && $params) {
				$translated[$type] = array_merge($translated[$type], $params);
			}
		}
		$this->verifyTranslated($translated);
		return array($translated['query_parameters'], $translated['update_parameters']);
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

	protected function validateSort($sort) {
		if (!is_array($sort) || array_filter($sort, function ($one) {
				return $one != 1 && $one != -1;
			})) {
			throw new Billrun_Exceptions_Api($this->errorBase + 3, array(), 'Illegal sort parameter');
		}
	}

	/**
	 *
	 * @param string $tpl the default tpl the controller used; this will be override to use the general admin layout
	 * @param array $parameters parameters of the view
	 *
	 * @return string the render layout including the page (component)
	 */
	protected function render($tpl, array $parameters = array()) {
		return $this->getView()->render('index.phtml', $parameters);
	}

}
