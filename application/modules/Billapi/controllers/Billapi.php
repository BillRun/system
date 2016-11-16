<?php

abstract class BillapiController extends Yaf_Controller_Abstract {

	protected $output;
	protected $collection;
	protected $action;

	public function indexAction() {
		$data = $this->getRequest()->getRequest();
		//username=shani2&roles=%5B%22admin%22%5D&password=qqqqqqq1
		$config = $this->getActionConfig();
		$updatedData = $this->validateRequest($data, $config);
		$entityModel = $this->getModel();
		$res = $entityModel->{$this->action}($updatedData);
		$this->output->status = (int) $res;
	}

	public function init() {
		$request = $this->getRequest();
		$this->collection = $request->getParam('collection');
		$this->action = strtolower($request->getParam('action'));
		$this->output = new stdClass();
		$this->getView()->output = $this->output;
		Yaf_Loader::getInstance(APPLICATION_PATH . '/application/modules/Billapi')->registerLocalNamespace("Models");
	}

	/**
	 * Get the right model, depending on the requested collection
	 * @return \Models_Entity
	 */
	protected function getModel() {
		$modelPrefix = 'Models_';
		$className = $modelPrefix . ucfirst($this->collection);
		if (!@class_exists($className)) {
			$className = $modelPrefix . ucfirst('Entity');
		}
		$params = array(
			'collection' => $this->collection,
		);
		return new $className($params);
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
	 * @param array $data the input
	 * @param array $parametersSettings the relevant config
	 * @return array
	 * @throws Billrun_Exceptions_Api
	 * @throws Billrun_Exceptions_InvalidFields
	 */
	protected function validateRequest(&$data, $parametersSettings) {
		$options = array();
		foreach (Billrun_Util::getFieldVal($parametersSettings['parameters'], array()) as $param) {
			$name = $param['name'];
			if (isset($param['mandatory']) && $param['mandatory'] && !isset($data[$name])) {
				throw new Billrun_Exceptions_Api($parametersSettings['error_base'] + 1, array(), 'Mandatory parameter ' . $name . ' missing');
			}
			$options['fields'][] = array(
				'name' => $name,
				'type' => $param['type'],
				'preConversions' => isset($param['pre_conversion']) ? $param['pre_conversion'] : [],
				'postConversions' => isset($param['post_conversion']) ? $param['post_conversion'] : [],
				'options' => [],
			);
		}
		$translatorModel = new Api_TranslatorModel($options);
		$ret = $translatorModel->translate($data);

		$translated = $ret['data'];
		Billrun_Factory::log("Translated result: " . print_r($ret, 1));
		if (!$ret['success']) {
			throw new Billrun_Exceptions_InvalidFields($translated);
		}
		return $translated;
	}

}
