<?php

/**
 * @package         Mongodloid
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */
class Mongodloid_Query implements IteratorAggregate {

	private $_collection;
	private $_operators = array(
		'==' => 'equals',
		'>' => 'greater',
		'>=' => 'greaterEq',
		'<' => 'less',
		'<=' => 'lessEq',
		'SIZE' => 'size',
		'EXISTS' => 'exists',
		'NOT EXISTS' => 'notExists',
		'NOT IN' => 'notIn',
		'IN' => 'in',
		'ALL' => 'all',
		'!=' => 'notEq',
		'%' => 'mod',
		'WHERE' => 'where',
	);
	private $_mongoOperators = array(
		'greater' => '$gt',
		'greaterEq' => '$gte',
		'less' => '$lt',
		'lessEq' => '$lte',
		'notEq' => '$ne',
		'in' => '$in',
		'notIn' => '$nin',
		'all' => '$all',
		'size' => '$size',
		'exists' => '$exists',
		'notExists' => '$exists',
		'mod' => '$mod',
	);
	private $_params = array();
	
	private $_project = array();

	private function _parseQuery($str) {
		$exprs = preg_split('@ AND |&&@i', $str);
		foreach ($exprs as $expr) {
			if (preg_match('@(?<left>.*?)(?<operator>%|==|>=|>|<=|<|!=|NOT EXISTS|EXISTS|SIZE|NOT IN|IN|ALL|WHERE)(?<right>.*)@', $expr, $matches)) {
				$right = trim($matches['right']);
				$func = $this->_operators[$matches['operator']];
				if ($matches['operator'] == '%') {
					$right = array_map(function($v) {
						return (int) trim($v);
					}, explode('==', $right));
				} else if ($matches['operator'] == 'EXISTS') {
					$right = true;
				} else if ($matches['operator'] == 'NOT EXISTS') {
					$right = false;
				}
				if (is_numeric($right)) {
					$right = (float) $right;
				}
				$this->$func(trim($matches['left']), $right);
			}
		}
	}

	/**
	 * Create a new instance of the query object.
	 * @param Mongodloid_Collection $collection - Collection for the query to reference.
	 */
	public function __construct(Mongodloid_Collection $collection) {
		$this->_collection = $collection;
	}

	public function where($what, $b = null) {
		if ($b)
			$what = $b;
		return $this->query(array(
				'$where' => $what
		));
	}

	public function equals($key, $value) {
		return $this->query($key, $value);
	}

	public function __call($name, $param) {
		if ($name == 'exists')
			$param[1] = true;
		else if ($name == 'notExists')
			$param[1] = false;
		else if ($name == 'mod' && isset($param[2]))
			$param[1] = array($param[1], $param[2]);


		if ($this->_mongoOperators[$name]) {
			if (is_string($param[1])) {
				$param[1] = array_map(function($v) {
					$v = trim($v);
					if (is_numeric($v))
						return (float) $v;
					return $v;
				}, explode(',', trim($param[1], '()')));
			}
			return $this->query(array(
					$param[0] => array(
						$this->_mongoOperators[$name] => $param[1]
					)
			));
		}
		throw new Mongodloid_Exception(__CLASS__ . '::' . $name . ' does not exist and hasn\'t been trapped in call');
	}

	public function count() {
		return $this->cursor()->count();
	}

	/**
	 * Get the cursor pointing to the collection based on the current query.
	 * @return \Mongodloid_Cursor
	 */
	public function cursor() {
		// 2nd argument due to new mongodb driver (PHP7+)
		$cursor = $this->_collection->find($this->_params, $this->_project)/*, $this->_collection->getWriteConcern('wtimeout')*/;
		$cursor->setRawReturn(false);
		return $cursor;
		
	}

	public function query($key, $value = null) {
		if (is_array($key)) {
			foreach ($key as $k => $v) {
				if (is_array($v) && isset($this->_params[$k]) && is_array($this->_params[$k]))
					$this->_params[$k] += $v;
				else
					$this->_params[$k] = $v;
			}
		} else if ($value) {
			$this->_params[$key] = $value;
		} else if (is_string($key)) {
			$this->_parseQuery($key);
		}

		return $this;
	}

	public function getIterator() {
		return $this->cursor();
	}
	
	public function project($project) {
		$this->_project = $project;
		return $this;
	}

}
