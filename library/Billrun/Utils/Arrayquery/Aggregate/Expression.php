<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of Expression
 *
 * @author eran
 */
class Billrun_Utils_Arrayquery_Aggregate_Expression {

	protected $mapping = array(
		'$sum' => '_sum',
		'$divide' => '_divide',
		'$multiply' => '_multiply',
		'$subtract' => '_subtract',
		'$push' => '_push',
		'$first' => '_first',
		'$last' => '_last',
		'$cond' => '_cond',
		'$substr' => '_substr',
		'__aggregate' => '_aggregate',
		'__callback' => '_callback'
	);

	public function __construct($mapping = array()) {
		$this->loadMapping($mapping);
	}

	/**
	 *
	 * @param type $mapping
	 */
	public function loadMapping($mapping) {
		if ($mapping) {
			$this->mapping = $mapping;
		}
	}

	/**
	 *
	 * @param type $data
	 * @param type $expression
	 * @return type
	 */
	public function evaluate($data, $expression, $pastValue = FALSE) {
		$ret = array();
		if(is_array($expression)) {
			foreach($expression as  $key => $value) {
				//If the vkey is operation one result should be returned  if it not an opertion then it should be added to the results by key.
				if(isset($this->mapping[$key]) && method_exists($this, $this->mapping[$key])) {
					$ret = $this->{$this->mapping[$key]}($data, $value,	$pastValue);
				} else {
					$ret[$key] = $this->evaluate($data, $value, $pastValue[$key]);
				}
			}
		} else {
			//if theres no operator assume field value path query
			$ret = Billrun_Utils_Arrayquery_Aggregate::getFieldValue($data, $expression);
		}
		return $ret;
	}


		/**
	*
	*/
	protected static function clearLeadingDollar($fieldsWithDollar) {
		if(!is_array($fieldsWithDollar)) {
			return preg_replace('/^\$/', '', $fieldsWithDollar);
		}
		foreach($fieldsWithDollar as &$value) {
			$value = preg_replace('/^\$/', '', $value);
		}
		return $fieldsWithDollar;
	}

	//======================================= instancing logic  ==============================
	protected function _first($data, $expression, $pastValue = FALSE) {
		$result = $pastValue;
		if(empty($result)) {
			$result = $this->evaluate($data,$expression);
		}
		return $result;
	}

	protected function _last($data, $expression, $pastValue = FALSE) {
		$result = $pastValue ;
		$last = $this->evaluate($data, $expression);
		if(empty($last)) {
			$result = $last;
		}
		return $result;
	}
	
	protected function _push($data, $expression, $pastValue = FALSE) {
		$result = empty($pastValue) ? array() : is_array($pastValue)  ? $pastValue : [$pastValue];
		if(is_array($expression)) {
			foreach($expression as $dstKey => $subExpression) {
				$addedData[$dstKey] = $this->evaluate($data, $subExpression);
			}
		} else {
			$addedData = $expression === 1 ? $data : $this->evaluate($data, $expression);
		}
		$result[] = $addedData;
		return $result;
	}

	//======================================= Arithmetic logic  ==============================

	protected function _sum($data, $expression, $pastValue = 0) {
		$result = $pastValue;
		$expression = is_array($expression) && !Billrun_Util::isAssoc($expression) ? $expression : array($expression);

		foreach ($expression as $key) {
			$result += $this->evaluate($data, $key);
		}

		return $result;
	}

	protected function _subtract($data, $expression, $pastValue = 0) {
		$result = $pastValue;
		$expression = is_array($expression) ? $expression : array($expression);

		foreach ($expression as $key) {
			$result -= $this->evaluate($data,$key);
		}

		return $result;
	}

	protected function _multiply($data, $expression, $pastValue = 0) {
		$expression = is_array($expression) ? $expression : array($expression);
		$result = Billrun_Util::getIn( $data, Billrun_Utils_Arrayquery_Aggregate::clearLeadingDollar(array_shift($expression)) );
		foreach ($expression as $key) {
			$result *= $this->evaluate($data, $key);
		}

		return $result + $pastValue;

	}

	protected function _divide($data, $expression, $pastValue = 0) {
		$expression = is_array($expression) ? $expression : array($expression);
		$result = Billrun_Util::getIn( $data, Billrun_Utils_Arrayquery_Aggregate::clearLeadingDollar(array_shift($expression)) );
		foreach ($expression as $key) {
			$result /= $this->evaluate($data, $key);
		}

		return $result + $pastValue;

	}
	
	//======================================= String operations ===============================
	
	protected function _substr($data, $expression, $pastValue = 0) {
		return substr($this->evaluate($data,array_shift($expression)), array_shift($expression), array_shift($expression));
	}

	//======================================= Expression logic  ===============================

	protected function _cond($data, $expression) {
		$condition = array_shift($expression);
		$truthValue = array_shift($expression);
		$falseValue = array_shift($expression);
		$expressionCheck = new Billrun_Utils_Arrayquery_Expression();
		return $this->evaluate($data, Billrun_Utils_Arrayquery_Query::exists(array($data), $condition) ?  $truthValue : $falseValue, array());

	}
	
	//==================================== Programatic extenstions logic (Unsupported by mongo) =======================
	
	protected function _callback($data, $expression, $pastValue = FALSE) {
		$arr = [];
		foreach ($expression['arguments'] as $arg) {
			$arr[] = $arg;
		}
		foreach ($expression['extra_params'] as $key => $value) {
			$arr[] = $value;
		}
		return empty($expression['callback']) ? FALSE : call_user_func_array($expression['callback'],$arr);
	}
	
	protected function _aggregate($data, $expression, $pastValue) {
		$aggregateObject = new Billrun_Utils_Arrayquery_Aggregate();
		return $aggregateObject->aggregate(array_diff_key($expression,array('previous_field'=>1)), array($data),@$pastValue[$expression['previous_field']]);
	}

}
