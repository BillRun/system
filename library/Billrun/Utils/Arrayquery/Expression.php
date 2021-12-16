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
class Billrun_Utils_Arrayquery_Expression {
	
	protected $mapping = array(
		'$gt' => '_gt',
		'$gte' => '_gte',
		'$lt' => '_lt',
		'$lte' => '_lte',
		'$eq' => '_equal',
		'$ne' => '_neq',
		'$in' => '_in',
		'$nin' => '_nin',
		'$all' => '_covers',
		'$and' => '_and',
		'$or' => '_or',
		'$not' => '_not',
		'$exists' => '_exists',
		'$regex' => '_regex',
		'*' => '_search',
		'**' => '_deepSearch',
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
			$this->mapping =  $mapping;
		}
	}
	
	/**
	 * 
	 * @param type $field
	 * @param type $expression
	 * @return type
	 */
	public function evaluate($field, $expression) {
		$ret = true ;
		if(is_array($expression)) {
			foreach($expression as  $key => $value) {
				if(isset($this->mapping[$key]) && method_exists($this, $this->mapping[$key])) {
					
					$ret &= $this->{$this->mapping[$key]}($field,$value);
					
				} else if (isset($value)) {

					$fieldVal = $field instanceof ArrayAccess || is_array($field) ? @$field[$key] : $field;
					$ret &= $this->evaluate($fieldVal, $value);
				} else {
					$ret = FALSE;
				}
			}
		} else {
			//if theres no operator assume equal or in operator
			$ret &= $this->_equal($field, $expression) 
					||
					is_array($field) && $this->_in($field, $expression);
		}
		return $ret;
	}
	
	
	//======================================= Binary logic  ==============================
	
	protected function _not($field, $expression) {
		return !$this->evaluate($field, $expression);
	}
	
	protected function _or($field, $expression) {
		$ret = false;
		foreach ($expression as  $expr) {
			$ret |= $this->evaluate($field, $expr);
		}
		return $ret;
	}	
	
	protected function _and($field, $expression) {
		$ret = true;
		foreach ($expression as $expr) {
			$ret &= $this->evaluate($field, $expr);
		}
		return $ret;
		
	}
	
	//======================================= Basic logic  ===============================
	
	protected function _gt($field, $value) {
		return ($field > $value);
	}
	
	protected function _gte($field, $value) {
		return ($field >= $value);
	}
	
	protected function _lt($field, $value) {
		return ($field < $value);
	}
	
	protected function _lte($field, $value) {
		return ($field <= $value);
	}
	
	protected function _neq($field, $value) {
		return !$this->_equal($field, $value);
	}
	
	protected function _equal($field, $value) {
		return ($field == $value);
	}
	
	//======================================= Complex expressions ===============================
	
	protected function _in($field, $value) {
		return  is_array($value) && is_array($field) && !empty(array_intersect($value,$field))
				|| is_array($value) && in_array($field,$value, true)
				|| is_array($field) && in_array($value,$field, true)
				|| $this->_equal($field, $value);
	}
	
	protected function _nin($field, $value) {
		return  !$this->_in($field, $value);
	}
	
	protected function _covers($field, $value) {
		return is_array($value) && count($value) == count(array_filter($value,function($val) use ($field) { 
			return $this->evaluate($field, $val);
		}));
	}
	/**
	 *
	 * @param type $field
	 * @param type $value
	 * @return type
	 */
	protected function _exists($field, $value) {
		return $value  ^ !isset($field);
	}
	
	/**
	 * compare field to regex or array values to regex
	 * @param type $field
	 * @param type $value
	 * @return type
	 */
	protected function _regex($field, $value) {
		$value = preg_match('/^\/.*\/\w*$/', $value) ? $value : '/' .$value.'/';
		
		$arrayRegexFunc = function($subject) use ($value) {
			return preg_match($value, $subject);
		};
		
		return  (is_array($field) && !empty(array_filter($field, $arrayRegexFunc)))
					|| 
				preg_match($value, $field);
	}
	
	//======================================= Searching logic ==================================
	
	/**
	 * preform a shallow search in an array
	 * @param type $field
	 * @param type $value
	 */
	protected function _search($field, $value) {
		$ret = false;
		if($field instanceof Traversable || is_array($field)) {
			foreach($field as $subfield) {
				if( $ret |= $this->evaluate($subfield, $value)) {
					break;
				}
			}
		}
		
		return $ret;
	}
	
	/**
	 * preform a deep search in array try to match all nested fields to a given expression.
	 * @param type $field the  array  the  neeto searched
	 * @param type $value the expression to match the  array to 
	 */
	protected function _deepSearch($field, $value) {
		$ret = false;
		if(!is_array($field)) {
			return $this->evaluate($field, $value);
		}
		foreach($field as $subfield) {
			$ret |= $this->evaluate($subfield, $value);
			if(is_array($subfield) && !$ret) {
				$ret |= $this->_deepSearch($subfield, $value);
			}
			if($ret) {	break;	}
		}
		
		return $ret;
	}

	//==================================== Programatic extenstions logic =======================
	/**
	 * This is
	 * @param type $data
	 * @param type $expression
	 * @param type $pastValue
	 * @return type
	 */
	protected function _callback($field, $value) {
		return empty($value['callback']) ? FALSE : call_user_func_array($value['callback'],array($field,$value['arguments']));
	}
}
