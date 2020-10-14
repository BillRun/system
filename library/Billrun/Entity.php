<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2020 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing general entity abstract implementation
 * Handles to load the data from the DB.
 * Provide entity's functionality
 *
 * @package  Billing
 * @since    5.12
 */
abstract class Billrun_Entity {

	/**
	 * container of entity's data
	 * 
	 * @var array
	 */
	protected $data = null;
	
	/**
	 * load time
	 *
	 * @var unixtimestamp
	 */
	protected $time;

	public function __construct(array $params = []) {
		$this->time = $params['time'] ?? time();
		$this->load($params);
	}

	/**
	 * get DB collection
	 *
	 * @return Mongodloid_Collection
	 */
	public abstract static function getCollection();
	
	/**
	 * get query to load the entities by the given parameters
	 *
	 * @param  array $params
	 * @return array
	 */
	protected abstract function getLoadQueryByParams($params = []);

	/**
	 * load the entity from the DB
	 * 
	 * @param array $params the parameters to load by
	 */
	protected function load($params = []) {
		if (isset($params['data'])) {
			$this->data = $params['data'] instanceof Mongodloid_Entity ? $params['data']->getRawData() : $params['data'];
			return true;
		}

		if (isset($params['id']) || isset($params['_id'])) {
			$id = $params['id'] ?? $params['_id'];
			$this->data = static::getCollection()->findOne($id)->getRawData();
		} else {
			$query = $this->getLoadQuery($params);
			$sort = $this->getLoadSort($params);
			if (!$query) {
				// throw exception
				return false;
			}
	
			$this->data = static::getCollection()->find($query)->sort($sort)->limit(1)->getNext();
		}
		
		if (!$this->data) {
			// throw exception
			return false;
		}
		
		return true;
	}
	
	/**
	 * get the query to load the entity by
	 *
	 * @param  array $params
	 * @return array
	 */
	protected function getLoadQuery($params = []) {
		$time = $params['time'] ?? $this->time;
		$query = array_merge(Billrun_Utils_Mongo::getDateBoundQuery($time), $this->getLoadQueryByParams($params));
		return $query;
	}
	
	/**
	 * get the query to sort matching entities by
	 *
	 * @param  array $params
	 * @return array
	 */
	protected function getLoadSort($params = []) {
		return [];
	}
	
	/**
	 * get entity field's value
	 *
	 * @param  string $name
	 * @param  mixed $default
	 * @return mixed
	 */
	public function get($name, $default = null) {
		return $this->data->get($name, $default);
	}
		
	/**
	 * get entity entire data
	 *
	 * @return array
	 */
	public function getData() {
		return $this->data;
	}

}
