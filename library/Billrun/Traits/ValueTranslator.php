<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2019 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Translate value using given translation mapping.
 * Can be used for place-holders.
 */
trait Billrun_Traits_ValueTranslator {
	
	protected $translationMapping = null;

	/**
	 * get mapping to use for translation
	 * 
	 * @param array $params
	 */
	public abstract function getTranslationMapping($params = []);
	
	/**
	 * Translate given value
	 * 
	 * @param string $valueToTranslate
	 * @param array $params
	 * @return string
	 */
	public function translateValue($valueToTranslate, $data = [], $params = []) {
		$mapping = $this->getMapping($valueToTranslate, $data, $params);
		if (!$mapping) {
			return $valueToTranslate;
		}
		
		Billrun_Factory::dispatcher()->trigger('beforeValueTranslation', [&$valueToTranslate, &$mapping, $this]);
		$value = '';
		
		if (!is_array($mapping)) {
			$value = Billrun_Util::getIn($data, $mapping, $mapping);
		} else if(isset ($mapping['hard_coded'])) {
			$value = $mapping['hard_coded'];
		} else if (isset($mapping['field'])) {
			$value = Billrun_Util::getIn($data, $mapping['field'], '');
		} else if (isset($mapping['func'])) {
			$funcName = $mapping['func']['func_name'];
			if (!method_exists($this, $funcName)) {
				Billrun_Log::getInstance()->log('ValueTranslator: mapping function "' . $funcName . '" does not exist', Zend_log::WARN);
			} else {
				$value = $this->{$funcName}($valueToTranslate, $mapping, $data, $params);
			}
		} else {
			Billrun_Log::getInstance()->log('ValueTranslator: invalid mapping: ' . print_R($mapping, 1), Zend_log::WARN);
		}
		
		Billrun_Factory::dispatcher()->trigger('afterValueTranslation', [&$value, &$valueToTranslate, &$mapping, $this]);

		if (!is_null($value)) {
			$value = $this->formatValue($value, $valueToTranslate, $mapping, $params);
		}
		
		return $value;
	}
	
	/**
	 * Get mapping to use for translation
	 * 
	 * @param string $value
	 * @param array $params
	 * @return array
	 */
	protected function getMapping($value, $data, $params =[]) {
		if (is_null($this->translationMapping)) {
			$this->translationMapping = $this->getTranslationMapping();
		}
		
		return $this->translationMapping[$value] ?? false;
	}
	
	/**
	 * Format value
	 * 
	 * @param string  $value
	 * @param string $valueToTranslate
	 * @param array $mapping
	 * @param array $params
	 * @return string
	 */
	public function formatValue($value, $valueToTranslate, $mapping, $params) {
		Billrun_Factory::dispatcher()->trigger('beforeValueTranslationFormat', [&$value, $valueToTranslate, $mapping, $this]);
		
		if (isset($mapping['format']['regex'])) {
			$value = preg_replace($mapping['format']['regex'], '', $value);
		}
		
		if (isset($mapping['format']['date'])) {
			$value = $this->formatDate($value, $mapping);
		}
		
		if (isset($mapping['format']['number'])) {
			$value = $this->formatNumber($value, $mapping);
		}
		
		if (isset($mapping['padding'])) {
			$padding = Billrun_Util::getIn($mapping, 'padding.character', ' ');
			$length = Billrun_Util::getIn($mapping, 'padding.length', strlen($value));
			$padDirection = strtolower(Billrun_Util::getIn($mapping, 'padding.direction', 'left')) == 'right' ? STR_PAD_RIGHT : STR_PAD_LEFT;
			$value = str_pad($value, $length, $padding, $padDirection);
		}
		
		Billrun_Factory::dispatcher()->trigger('afterValueTranslationFormat', [&$value, $valueToTranslate, $mapping, $this]);
		
		return $value;
	}
	
	/**
	 * Format a date value
	 * 
	 * @param mixed $date
	 * @param array $mapping
	 * @return string
	 */
	protected function formatDate($date, $mapping) {
		if ($date instanceof MongoDate) {
			$date = $date->sec;
		} else if (is_string($date)) {
			$date = strtotime($date);
		}
		$dateFormat = Billrun_Util::getIn($mapping, 'format.date', 'YmdHis');
		if ($dateFormat === 'unixtimestamp') {
			return $date;
		}
		return date($dateFormat, $date);
	}
	
	/**
	 * Format a number value
	 * 
	 * @param mixed $date
	 * @param array $mapping
	 * @return string
	 */
	protected function formatNumber($number, $mapping) {
		$multiply = Billrun_Util::getIn($mapping, 'format.number.multiply', 1);
		$decimals = Billrun_Util::getIn($mapping, 'format.number.decimals', 0);
		$dec_point = Billrun_Util::getIn($mapping, 'format.number.dec_point', '.');
		$thousands_sep = Billrun_Util::getIn($mapping, 'format.number.thousands_sep', ',');
		return number_format(($number * $multiply), $decimals, $dec_point, $thousands_sep);
	}
}
