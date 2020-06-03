<?php

class Billrun_Utils_ForeignFields {

	public static function getForeignFields($entity) {
		$foreignFieldsConf = array_filter(Billrun_Factory::config()->getConfigValue('lines.fields', array()), function($value) use ($entity) {
			return Billrun_Util::getIn($value, 'foreign.entity') == $entity;
		});
		
		return array_values($foreignFieldsConf);
	}

}
