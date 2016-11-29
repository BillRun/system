<?php

abstract class BillapiController extends Yaf_Controller_Abstract {

	protected $output;
	protected $collection;
	protected $action;

	public function indexAction() {
		$request = $this->getRequest();
		$query = json_decode($request->get('query'), TRUE);
		$update = json_decode($request->get('update'), TRUE);
		list($translatedQuery, $translatedUpdate) = $this->validateRequest($query, $update);
		$entityModel = $this->getModel();
		$res = $entityModel->{$this->action}($translatedQuery, $translatedUpdate);
		$this->output->status = 1;
		$this->output->details = $res;
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
			foreach (Billrun_Util::getFieldVal($parametersSettings[$type], array()) as $param) {
				$name = $param['name'];
				if (!isset($params[$name])) {
					if (isset($param['mandatory']) && $param['mandatory']) {
						throw new Billrun_Exceptions_Api($parametersSettings['error_base'] + 1, array(), 'Mandatory ' . str_replace('_parameters', '', $type) . ' parameter ' . $name . ' missing');
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
			}
			if ($options['fields']) {
				$translatorModel = new Api_TranslatorModel($options);
				$ret = $translatorModel->translate($params);
				$translated[$type] = $ret['data'];
				Billrun_Factory::log("Translated result: " . print_r($ret, 1));
				if (!$ret['success']) {
					throw new Billrun_Exceptions_InvalidFields($translated[$type]);
				}
			} else {
				$translated[$type] = array();
			}
		}
		return array($translated['query_parameters'], $translated['update_parameters']);
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
