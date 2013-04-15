<?php

/**
Copyright (c) 2009, Valentin Golev
All rights reserved.

Redistribution and use in source and binary forms, with or without modification,
are permitted provided that the following conditions are met:

    * Redistributions of source code must retain the above copyright notice,
      this list of conditions and the following disclaimer.

    * Redistributions in binary form must reproduce the above copyright notice,
      this list of conditions and the following disclaimer in the documentation
      and/or other materials provided with the distribution.

    * The names of contributors may not be used to endorse or promote products
      derived from this software without specific prior written permission.

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR
ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
(INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON
ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
(INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
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
		else if ($name == 'mod' && $param[2])
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
		throw new Mongodloid_Exception(__CLASS__ . '::' . $name . ' does not exists and hasn\'t been trapped in call');
	}

	public function count() {
		return $this->cursor()->count();
	}

	public function cursor() {
		return new Mongodloid_Cursor($this->_collection->find($this->_params));
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

}