<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * This class defines a query logic that is comparable to mongo query but  can apply to php arrays
 *
 * @author eran
 */
class Billrun_Utils_Arrayquery_Query {

	const MAX_ARRAY_LENGTH = 1024;

	public static function query($array, $rawQuery, $ignoreMaxLength = FALSE) {
		if(!$ignoreMaxLength && count($array) > static::MAX_ARRAY_LENGTH ) {
			Billrun_Factory::log('Cannot query array bigger than : '.static::MAX_ARRAY_LENGTH,  Zend_Log::ALERT);
			return FALSE;
		}
		$query = static::translateQueryKeys($rawQuery);
		return static::_query($array, $query);
	}

	public static function exists($array, $rawQuery, $ignoreMaxLength = FALSE) {
		if(!$ignoreMaxLength && count($array) > static::MAX_ARRAY_LENGTH) {
			Billrun_Factory::log('Cannot query  array bigger than : '.static::MAX_ARRAY_LENGTH,  Zend_Log::ALERT);
			return FALSE;
		}
		$query = static::translateQueryKeys($rawQuery);
		return static::_exists($array, $query) ? true : false;
	}

	protected  static function translateQueryKeys($query,$separator = '.') {
		if (!is_array($query) && !is_object($query)) {
			return array();
		}
		$translatedQuery = array();
		foreach($query as  $key => $value) {
			$pos = strpos($key, $separator);
			if($pos) {
				$left = substr($key,$pos+1);
				$key = substr($key,0,$pos);
				$value = static::translateQueryKeys(array( $left => $value ), $separator);
			}
			$translatedQuery[$key] = is_array($value) ?
										array_merge(Billrun_Util::getFieldVal($translatedQuery[$key],array()),static::translateQueryKeys( $value , $separator))
										: $value;
		}
		return $translatedQuery;
	}

	protected static function _query($array, $query) {

		$expression = new Billrun_Utils_Arrayquery_Expression(Billrun_Factory::config()->getConfigValue('array_query.expressions_mapping',array()));
		//evealuate document 
		if (Billrun_Util::isAssoc($array)) {
			$ret = $expression->evaluate($array, $query) ? $array : array();
		} else {//evealuate documents
			foreach($array as $key => $value) {
				if($expression->evaluate($value, $query)) {
					$ret[] = $value;
				} else if(is_array($value) && isset($query[$key])&& !empty($tmpRet = self::query($value, $query[$key])) ) {
					$ret[$key]= $tmpRet;
				}
			}
		}
		return $ret;
	}

	protected static function _exists($array, $query) {
		$expression = new Billrun_Utils_Arrayquery_Expression(Billrun_Factory::config()->getConfigValue('array_query.expressions_mapping',array()));
		//evealuate document 
		if (Billrun_Util::isAssoc($array)) {
			$ret = $expression->evaluate($array, $query) ? TRUE : FALSE;
		} else { //evealuate documents
			foreach($array as $value) {
				$ret |= $expression->evaluate($value, $query);
				if($ret) {	break;	}
			}
		}
		return $ret;
	}
}
