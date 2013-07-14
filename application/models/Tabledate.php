<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Table Date model class to pull data from database for collections with from/to fields
 *
 * @package  Models
 * @subpackage Table
 * @since    0.5
 */
class TabledateModel extends TableModel {

	/**
	 * the date we filter the table
	 * 
	 * @var Zend_Date
	 */
	protected $date;

	/**
	 * constructor
	 * 
	 * @param array $params of parameters to preset the object
	 */
	public function __construct(array $params = array()) {
		parent::__construct($params);
		if (isset($params['date'])) {
			$this->date = $params['date'];
		}
	}

	/**
	 * Get the data resource
	 * 
	 * @return Mongo Cursor
	 */
	public function getData() {
		$dateInput = new MongoDate($this->date->getTimestamp());

		$resource = $this->collection->query()
				->lessEq('from', $dateInput)
				->greaterEq('to', $dateInput)
				->cursor()->sort($this->sort)->skip($this->offset())->limit($this->size);
		return $resource;
	}

	public function getItem($id) {
		$entity = parent::getItem($id);
		$entity['from'] = (new Zend_Date($entity['from']->sec))->toString('YYYY-MM-dd HH:mm:ss');
		$entity['to'] = (new Zend_Date($entity['to']->sec))->toString('YYYY-MM-dd HH:mm:ss');
		return $entity;
	}

	/**
	 * Check if a new entity could replace it
	 * @param type $param
	 */
	public function isReplacable($entity) {
		$to_date = new MongoDate(strtotime($entity['to']));
		if (!$to_date) {
			die("date error");
		}
		$result = $this->collection
			->query($this->search_key, $entity[$this->search_key])
			->cursor()
			->sort(array('to' => -1))
			->limit(1)
			->current();
		return strval($result['_id']) == strval($entity['_id']);
	}

	public function getProtectedKeys($type) {
		$parent_protected = parent::getProtectedKeys($type);
		if ($type == 'update') {
			return array_merge($parent_protected, array("from", "to", $this->search_key));
		} else if ($type == 'close_and_new') {
			return array_merge($parent_protected, array($this->search_key));
		}
		return $parent_protected;
	}

	public function closeAndNew($params) {
		//			$from = new Zend_Date($data['from'], null, 'he-IL');
		$new_from = new Zend_Date($params['from'], null, 'he-IL');
		if ($new_from->getTimestamp() < time()) {
			$new_from = new Zend_Date(null, null, 'he-IL');
		}

		// close the old line
		$mongoCloseTime = new MongoDate($new_from->getTimestamp() - 1);
		$closed_data = $params;
		unset($closed_data['from']);
		$closed_data['to'] = $mongoCloseTime;
		$this->save($closed_data);

		// open new line
		unset($params['_id']);
		$params['from'] = new MongoDate($new_from->getTimestamp());
		$params['to'] = new MongoDate($new_from->add(125, Zend_Date::YEAR)->getTimestamp());
		return $this->save($params);
	}

	public function duplicate($params) {
		$old_entity = $this->collection->findOne($params['_id']);
		if ($old_entity[$this->search_key] == $params[$this->search_key]) {
			die(json_encode("Please choose another key name"));
		}
		unset($params['_id']);
		$from = new Zend_Date($params['from'], null, 'he-IL');
		$to = new Zend_Date($params['to'], null, 'he-IL');
		$params['from'] = new MongoDate($from->getTimestamp());
		$params['to'] = new MongoDate($to->getTimestamp());
		return $this->save($params);
	}

}
