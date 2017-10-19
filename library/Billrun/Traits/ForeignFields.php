<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing tarit to add foreign fields logic to generated cdr/line
 *
 * @package  Trait
 * @since    0.5
 */

trait Billrun_Traits_ForeignFields  {
	/**
	 * This array  will hold all the  added foregin fields that  were added to the CDR/row/line.
	 */
	protected $addedForeignFields = array();
	
	
	protected function getAddedFoerignFields() {
		return $this->addedForeignFields;
	}
	
	protected function clearAddedForeignFields() {
		$this->addedForeignFields = array();
	}
	

	protected function getForeignFields($foreignEntities,$existsingFields = array()) {
		$this->clearAddedForeignFields();
		$foreignFieldsData = !empty($existsingFields) ? $existsingFields : array();
		$foreignFieldsConf = array_filter(Billrun_Factory::config()->getConfigValue('lines.fields', array()), function($value) {
			return isset($value['foreign']);	
		});
		
		foreach ($foreignFieldsConf as $fieldConf) {
			if (!empty($foreignEntities[$fieldConf['foreign']['entity']]) ) {
				if(!is_array($foreignEntities[$fieldConf['foreign']['entity']]) || Billrun_Util::isAssoc($foreignEntities[$fieldConf['foreign']['entity']])) {
					Billrun_Util::setIn($foreignFieldsData, $fieldConf['field_name'], $this->getForeginEntityFieldValue($foreignEntities[$fieldConf['foreign']['entity']], $fieldConf['foreign']['field']));
				} else {
					foreach ($foreignEntities[$fieldConf['foreign']['entity']] as $idx => $foreignEntity) {
						Billrun_Util::setIn($foreignFieldsData, $fieldConf['field_name'].'.'.$idx, $this->getForeginEntityFieldValue($foreignEntity, $fieldConf['foreign']['field']));
					}
				}
				$this->addedForeignFields[] = preg_replace('/\..+$/','',$fieldConf['field_name']);
			}
		}
		return $foreignFieldsData;
	}
	
	protected function getForeginEntityFieldValue($foreignEntity, $field) {
		if(is_object($foreignEntity) && method_exists($foreignEntity, 'getData')) {
			$foreignEntity = $foreignEntity->getData();
		}
		return Billrun_Util::getIn($foreignEntity,$field);
	}

}