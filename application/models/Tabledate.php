<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
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
	public function getData($filter_query = array()) {
		$cursor = $this->collection->query($filter_query)->cursor();
		$this->_count = $cursor->count();
		$resource = $cursor->sort($this->sort)->skip($this->offset())->limit($this->size);
		return $resource;
	}

	public function getItem($id) {
		$entity = parent::getItem($id);
		if (is_array($entity['from']) && isset($entity['from']->sec))
			$entity['from'] = (new Zend_Date($entity['from']->sec))->toString('dd-MM-YYYY HH:mm:ss');
		if (is_array($entity['to']) && isset($entity['to']->sec))
			$entity['to'] = (new Zend_Date($entity['to']->sec))->toString('dd-MM-YYYY HH:mm:ss');
		return $entity;
	}

	/**
	 * Check if a new entity could replace it
	 * @param type $param
	 */
	public function isLast($entity) {
		$to_date = new MongoDate(strtotime($entity['to']));
		if (!$to_date) {
			return $this->setError("date error") ;
			
		}
		$result = $this->getLastItem($entity[$this->search_key]);
		return strval($result['_id']) == strval($entity['_id']);
	}

	public function endsInFuture($entity) {
		$to_date = strtotime($entity['to']);
		return $to_date > time();
	}

	public function startsInFuture($entity) {
		$from_date = strtotime($entity['from']);
		return $from_date > time();
	}

	public function getProtectedKeys($entity, $type) {
		$parent_protected = parent::getProtectedKeys($entity, $type);
		if ($type == 'update') {
			if ($this->isLast($entity) && $this->endsInFuture($entity)) {
				return array_merge($parent_protected, array("from", $this->search_key));
			}
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
		$closed_data = $this->collection->findOne($params['_id'])->getRawData();
		$closed_data['to'] = $mongoCloseTime;
		$this->update($closed_data);

		// open new line
		$params[$this->search_key] = $closed_data[$this->search_key];
		unset($params['_id']);
		$params['from'] = new MongoDate($new_from->getTimestamp());
		$params['to'] = new MongoDate($new_from->add(125, Zend_Date::YEAR)->getTimestamp());
		return $this->update($params);
	}

	public function duplicate($params) {
		$from = new Zend_Date($params['from'], null, 'he-IL');
		$to = new Zend_Date($params['to'], null, 'he-IL');
		$params['from'] = new MongoDate($from->getTimestamp());
		$params['to'] = new MongoDate($to->getTimestamp());
		return parent::duplicate($params);
	}

	public function update($params) {
		if (isset($params['from']) && !$params['from'] instanceof MongoDate) {
			$params['from'] = new MongoDate(strtotime($params['from']));
		}
		if (isset($params['to']) && !$params['to'] instanceof MongoDate) {
			$params['to'] = new MongoDate(strtotime($params['to']));
		}
		return parent::update($params);
	}

	public function remove($params) {
		$entity = $this->collection->query($params)->cursor()->current();
		if (!$entity->isEmpty()) {
			$to = $entity['to'];
			$key_name = $entity[$this->search_key];
			$this->collection->removeEntity($entity);
			$last_item = $this->getLastItem($key_name);
			$this->collection->updateEntity($last_item, array('to' => $to));
		}
	}

	public function getLastItem($key_name) {
		$result = $this->collection
				->query($this->search_key, $key_name)
				->cursor()
				->sort(array('to' => -1))
				->limit(1)
				->current();
		$result->collection($this->collection);
		return $result;
	}

	public function getFilterFields() {
		$filter_fields = array(
			'date' => array(
				'key' => 'date',
				'db_key' => array('from', 'to'),
				'input_type' => 'date',
				'comparison' => array('$lte', '$gte'),
				'display' => 'Date',
				'default' => (new Zend_Date(null, null, new Zend_Locale('he_IL')))->toString('YYYY-MM-dd HH:mm:ss'),
			),
		);
		return array_merge($filter_fields, parent::getFilterFields());
	}

	public function applyFilter($filter_field, $value) {
		if ($filter_field['input_type'] == 'date' && is_array($filter_field['db_key'])) {
			if (is_string($value)) {
				$value = new MongoDate((new Zend_Date($value, null, new Zend_Locale('he_IL')))->getTimestamp());
				return array(
					'$and' => array(
						array(
							$filter_field['db_key'][0] => array(
								$filter_field['comparison'][0] => $value
							),
							$filter_field['db_key'][1] => array(
								$filter_field['comparison'][1] => $value
							),
						),
					),
				);
			}
		} else {
			return parent::applyFilter($filter_field, $value);
		}
	}

	public function getFilterFieldsOrder() {
		$filter_field_order = array(
			array(
				'date' => array(
					'width' => 2,
				),
			),
		);
		return $filter_field_order;
	}

	public function getSortFields() {
		$sort_fields = array(
			'from' => 'From',
			'to' => 'To',
		);
		return $sort_fields;
	}

}
