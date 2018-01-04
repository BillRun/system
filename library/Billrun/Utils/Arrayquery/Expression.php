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
	
	protected $mapping = array();
	
	public function __construct($mapping) {
		$this->loadMapping($mapping);
	}
	
	/**
	 * 
	 * @param type $mapping
	 */
	public function loadMapping($mapping) {
		$this->mapping =  $mapping;
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
					
				} else if(isset($field[$key])) {
					
					$ret &= $this->evaluate($field[$key], $value);
					
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
				|| is_array($value) && in_array($field,$value) 				
				|| is_array($field) && in_array($value,$field) 
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
	 * compare field to regex or array values to regex
	 * @param type $field
	 * @param type $value
	 * @return type
	 */
	protected function _regex($field, $value) {
		$arrayRegexFunc = function($subject) use ($value) {
			return preg_match('/' . $value . '/', $subject);
		};
		
		return  (is_array($field) && !empty(array_filter($field, $arrayRegexFunc)))
					|| 
				preg_match('/' . $value . '/', $field);
	}
	
	//======================================= Searching logic ==================================
	
	/**
	 * preform a shallow search in an array
	 * @param type $field
	 * @param type $value
	 */
	protected function _search($field, $value) {
		$ret = false;
		foreach($field as  $subfield) {
			if( $ret |= $this->evaluate($subfield, $value)) {	
				break;	
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
}
