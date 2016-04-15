<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of ServiceProviders
 *
 * @author lewis
 */
class ServiceprovidersModel extends TabledateModel {

	public function __construct(array $params = array()) {
		$params['collection'] = Billrun_Factory::db()->serviceproviders;
		parent::__construct($params);
		$this->service_providers_coll = Billrun_Factory::db()->serviceprovidersCollection();
	}

}
