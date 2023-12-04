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

	private $foreginFieldPrefix = 'foreign';

	/**
	 * This array  will hold all the  added foreign fields that  were added to the CDR/row/line.
	 */
	protected $addedForeignFields = array();
	
	
	protected function getAddedFoerignFields() {
		return array_keys($this->addedForeignFields);
	}
	
	protected function clearAddedForeignFields() {
		$this->addedForeignFields = array();
	}
	
	protected function getForeignFieldsFromConfig($entity = 'lines') {
		return array_filter(Billrun_Factory::config()->getConfigValue($entity.'.fields', array()), function($value) {
			return isset($value['foreign']);
		});
	}

	protected function getForeignFields($foreignEntities, $existsingFields = array(), $autoLoadEntities = FALSE, $fullData = array()) {
		$entity = $this->getForeignFieldsEntity();
		$foreignFieldsData = !empty($existsingFields) ? $existsingFields : array();
		$foreignFieldsConf = $this->getForeignFieldsFromConfig($entity);
		
		foreach ($foreignFieldsConf as $fieldConf) {
			if(!preg_match('/^'.$this->foreginFieldPrefix.'\./',$fieldConf['field_name'])) {
				Billrun_Factory::log("Foreign field configuration not mapped to foreign sub-field",Zend_Log::WARN);
				continue;
			}
			//should  we auto load foreign entites and we didn't loaded that entity before?
			if( $autoLoadEntities && empty($foreignEntities[$fieldConf['foreign']['entity']]) && empty(Billrun_Util::getIn($foreignFieldsData,$fieldConf['field_name']))
				&& (!is_array($autoLoadEntities) || in_array($fieldConf['foreign']['entity'],$autoLoadEntities)) ) {
				$entityValue = Billrun_Utils_Usage::retriveEntityFromUsage(array_merge($foreignFieldsData,$fullData), $fieldConf['foreign']['entity'],$fieldConf);
				if($entityValue != null) {
					$foreignEntities[$fieldConf['foreign']['entity']] = $entityValue;
				}
			}
			//Do  we  have the  requested foreign entity?
			if (!empty($foreignEntities[$fieldConf['foreign']['entity']]) ) {
				if(!is_array($foreignEntities[$fieldConf['foreign']['entity']]) || Billrun_Util::isAssoc($foreignEntities[$fieldConf['foreign']['entity']])) {
					//if the foreign entity has the  requested field retirve  it`s value.
					if($this->hasForeginEntityFieldValue($foreignEntities[$fieldConf['foreign']['entity']], $fieldConf['foreign'])) {
						$pathToInsert = $this->buildPathToInsert($fieldConf);
						Billrun_Util::setIn($foreignFieldsData, $pathToInsert, $this->getForeginEntityFieldValue($foreignEntities[$fieldConf['foreign']['entity']], $fieldConf['foreign']));
					}
				} else {
					//if there are multiple foreign  entites  go through each one of them and retrive it`s value/nonvalue and add them to an array  on the requesting row
					foreach ($foreignEntities[$fieldConf['foreign']['entity']] as $idx => $foreignEntity) {
						Billrun_Util::setIn($foreignFieldsData, $fieldConf['field_name'].'.'.$idx, $this->getForeginEntityFieldValue($foreignEntity, $fieldConf['foreign']));
					}
				}
				//Save the fields add so they can be queried what was updated later
				$this->addedForeignFields[preg_replace('/\..+$/','',$fieldConf['field_name'])] = true;
			}
		}
		return $foreignFieldsData;
	}

	/**
	 * Retrive the value of a field in a given foreign entity
	 */
	protected function getForeginEntityFieldValue($foreignEntity, $foreignConf) {
		if(is_object($foreignEntity) && method_exists($foreignEntity, 'getData')) {
			$foreignEntity = $foreignEntity->getData();
		}
		return $this->foreignFieldValueTranslation( Billrun_Util::getIn($foreignEntity, $foreignConf['field']), $foreignConf);
	}

	/**
	 * Check  of  a foreign entity has a given field
	 */
	protected function hasForeginEntityFieldValue($foreignEntity, $foreignConf) {
		if(is_object($foreignEntity) && method_exists($foreignEntity, 'getData')) {
			$foreignEntity = $foreignEntity->getData();
		}
		$notExisitingKey = 'FOREIGN_FIELD_DOES_NOT_EXISTS_'.rand(0,100000);

		return $notExisitingKey !== Billrun_Util::getIn($foreignEntity,  $foreignConf['field'],	$notExisitingKey );;
	}

	protected function foreignFieldValueTranslation($value, $foreignConf) {
		if(empty($foreignConf['translate'])) {
			return $value;
		}

		$translated = $value;
		switch($foreignConf['translate']['type']) {
			case 'unixTimeToString' : $translated = date(Billrun_Util::getFieldVal($foreignConf['translate']['format'],  Billrun_Base::base_datetimeformat),$value);
				break;
			case 'unixTimeToMongoDate' : $translated = new Mongodloid_Date($value);
				break;
			default: Billrun_Factory::log("Couldn't find translation function : {$foreignConf['translate']['type']}",Zend_Log::WARN);
		}

		return $translated;
	}
	
	protected function buildPathToInsert($foreignConf) {
		$entity = $foreignConf['foreign']['entity'];
		switch ($entity) {
			case 'tax':
				$pathToInsert = $foreignConf['foreign']['field'];
				break;
			default:
				$pathToInsert = $foreignConf['field_name'];
				break;
		}
		return $pathToInsert;
	}
	
	protected function getForeignFieldsEntity () {
		return 'lines';
	}

	protected function checkIfExistInForeignEntities($entity) {
		$foreignEntities = array_map(function($value) {
			return $value['foreign']['entity'];
		}, Billrun_Factory::config()->getConfigValue('lines.fields', array()));
		return in_array($entity, $foreignEntities) ? TRUE : FALSE;
	}
	
	protected function getForeignFieldsConfOfEntity($entity){
		return array_filter(Billrun_Factory::config()->getConfigValue($this->getForeignFieldsEntity() .'.fields', array()),  function($value) use($entity){
			return isset($value['foreign']['entity'])&& $value['foreign']['entity'] === $entity;	
		});
	}

}
