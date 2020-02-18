<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing calculator class that will unifiy several cdrs to s single cdr if possible.
 * The class is basic rate that can evaluate record rate by different factors
 * 
 * @package  calculator
 * 
 * @since    2.6
 *
 */
class Billrun_Calculator_Unify extends Billrun_Calculator {

	protected $unifiedLines = array();
	protected $unificationFields;
	protected $archivedLines = array();
	protected $unifiedToRawLines = array();
	protected $dateSeperation = "Ymd";
	protected $acceptArchivedLines = false;
	protected $protectedConcurrentFiles = true;
	protected $archiveDb;

	public function __construct($options = array()) {
		parent::__construct($options);
		$this->init();
		if (isset($options['date_seperation'])) {
			$this->dateSeperation = $options['date_seperation'];
		}

		if (isset($options['unification_fields'])) {
			$this->unificationFields = $options['unification_fields'];
		} else {
			// TODO: put in seperate config dedicate to unify
			$this->unificationFields = array(
				'ggsn' => array(
					'required' => array(
						'fields' => array('sid', 'aid', 'ggsn_address', 'arate', 'urt','sgsn_address'),
						'match' => array(
							'arate_key' => Billrun_Factory::config()->getConfigValue('ggsn.unify.required.arate_key_regex','/^INTERNET_BILL_BY_VOLUME$/'),
						),
					),
					'date_seperation' => 'Ymd',
					'stamp' => array(
						'value' => array('sgsn_address', 'ggsn_address', 'sid', 'aid', 'arate', 'imsi', 'plan', 'rating_group', 'billrun', 'rat_type', 'served_imeisv'), 
						'field' => array('in_plan', 'out_plan', 'over_plan', 'aprice'),
					),
					'fields' => array(
						'$set' => array('process_time'),
						'$setOnInsert' => array('urt', 'imsi', 'usagesb', 'usaget', 'aid', 'sid', 'ggsn_address', 'sgsn_address', 'rating_group', 'arate', 'plan', 'billrun', 'rat_type', 'served_imeisv'),
						'$inc' => array('usagev', 'aprice', 'apr', 'fbc_downlink_volume', 'fbc_uplink_volume', 'duration', 'in_plan', 'out_plan', 'over_plan'),
						'_array_map'=> [
                            [
                                'field' => 'addon_balances',
                                'to_field' => 'unified_addon_balances',
                                'keys' => ['package_id','billrun_month'],
                                'values' => [
                                    '$inc' => ['added_usage'],
                                    '$setOnInsert' => ['billrun_month','service_name','package_id']
                                ]
                            ]
						],
					),
				),
				'nsn' => array(
					'required' => array(
						'fields' => array('urt', 'record_type', 'in_circuit_group', 'in_circuit_group_name', 'out_circuit_group', 'out_circuit_group_name'),
						'match' => array(
							'classMethod' => 'isNsnLineLegitimate',
						),
					),
					'date_seperation' => 'Ymd',
					'stamp' => array(
						'value' => array('record_type', 'in_circuit_group', 'in_circuit_group_name', 'out_circuit_group', 'out_circuit_group_name', 'arate', 'usaget', 'calling_subs_last_ex_id', 'called_subs_last_ex_id','wholesale_rate_key'),
						'field' => array()
					),
					'fields' => array(
						'$set' => array('process_time'),
						'$setOnInsert' => array('urt', 'record_type', 'in_circuit_group', 'in_circuit_group_name', 'out_circuit_group', 'out_circuit_group_name', 'calling_subs_last_ex_id', 'called_subs_last_ex_id', 'arate', 'usaget','wholesale_rate_key'),
						'$inc' => array('usagev', 'duration'),
					),
				),
			);
		}

		if (isset($options['accept_archived_lines'])) {
			$this->acceptArchivedLines = $options['accept_archived_lines'];
		}

		if (isset($options['protect_concurrent_files'])) {
			$this->protectedConcurrentFiles = $options['protect_concurrent_files'];
		}

		// archive connection setting
		$this->archiveDb = Billrun_Factory::db(Billrun_Factory::config()->getConfigValue('archive.db'));
	}

	/**
	 * Initialize the data used for lines unification.
	 * (call this when you want to start unify again after the lines were saved to the DB)
	 */
	public function init() {
		$this->archivedLines = array();
		$this->unifiedToRawLines = array();
		$this->unifiedLines = array();
//		$this->activeBillrun = Billrun_Billrun::getActiveBillrun();
	}

	/**
	 * add a sigle row/line to unified line if there is no unified line then create one.
	 * @param array $rawRow the single row to unify.
	 * @return boolean true this can't fail (other then some php errors)
	 */
	public function updateRow($rawRow) {
		$newRow = $rawRow instanceof Mongodloid_Entity ? $rawRow->getRawData() : $rawRow;
		// we aligned the urt to one main timestamp to avoid DST issues; effect only unified data
		$newRow['urt'] = $newRow['urt'];
		$updatedRowStamp = $this->getLineUnifiedLineStamp($newRow);

		$rawRow['u_s'] = $updatedRowStamp;
		$this->archivedLines[$newRow['stamp']] = $rawRow->getRawData();
		$this->unifiedToRawLines[$updatedRowStamp]['remove'][] = $newRow['stamp'];

		if (($this->protectedConcurrentFiles && $this->isLinesLocked($updatedRowStamp, array($newRow['stamp']))) ||
				(!$this->acceptArchivedLines && $this->isLinesArchived(array($newRow['stamp'])))) {
			Billrun_Factory::log("Line {$newRow['stamp']} was already applied to unified line $updatedRowStamp", Zend_Log::NOTICE);
			return true;
		}

		$updatedRow = $this->getUnifiedRowForSingleRow($updatedRowStamp, $newRow);
		foreach ($this->unificationFields[$newRow['type']]['fields'] as $key => $fields) {
            if(method_exists($this, $key) ) {
                    $updatedRow = $this->{$key.'_new'}($newRow,$fields,$updatedRow);
            } else {
                foreach ($fields as $field) {
                    if ($key == '$inc' && isset($newRow[$field])) {
                        $updatedRow[$field] += $newRow[$field];
                    } else if ($key == '$set' && isset($newRow[$field])) {
                        $updatedRow[$field] = $newRow[$field];
                    }
                }
			}
		}
		$updatedRow['lcount'] += 1;
		$this->unifiedLines[$updatedRowStamp] = $updatedRow;
		$this->unifiedToRawLines[$updatedRowStamp]['update'][] = $newRow['stamp'];

		return true;
	}

	/**
	 * saved the single rows that were unified to the archive.
	 * @return array containing the rows that were failed when trying to save to the archive.
	 */
	public function saveLinesToArchive() {
		$failedArchived = array();
		$linesArchivedStamps = array();
		$archLinesColl = $this->archiveDb->linesCollection();
		$localLines = Billrun_Factory::db()->linesCollection();

		$archivedLinesCount = count($this->archivedLines);
		if ($archivedLinesCount > 0) {
			try {
				Billrun_Factory::log('Saving ' . $archivedLinesCount . ' source lines to archive.', Zend_Log::INFO);
				$archLinesColl->batchInsert($this->archivedLines, array('w' => 0)); // we put 0 in case insert was failed on previous run and this is recovery
				$this->data = array_diff_key($this->data, $this->archivedLines);
				$linesArchivedStamps = array_keys($this->archivedLines);
			} catch (Exception $e) {
				Billrun_Factory::log("Failed to insert to archive. " . $e->getCode() . " : " . $e->getMessage(), Zend_Log::ALERT);
				// todo: dump lines into file
			}
//			foreach ($this->archivedLines as $line) {
//				try {
//					$archLinesColl->insert($line, array('w' => 1));
//					$linesArchivedStamps[] = $line['stamp'];
//					unset($this->data[$line['stamp']]);
//				} catch (\Exception $e) {
//					if ($e->getCode() == '11000') {
//						Billrun_Factory::log("got duplicate line when trying to save line {$line['stamp']} to archive.", Zend_Log::ALERT);
//						$linesArchivedStamps[] = $line['stamp'];
//						unset($this->data[$line['stamp']]);
//					} else {
//						Billrun_Factory::log("Failed when trying to save a line {$line['stamp']} to the archive failed with: " . $e->getCode() . " : " . $e->getMessage(), Zend_Log::ALERT);
//						$failedArchived[] = $line;
//					}
//				}
//			}
			Billrun_Factory::log('Removing Lines from the lines collection....', Zend_Log::INFO);
			$localLines->remove(array('stamp' => array('$in' => $linesArchivedStamps)));
		}
		return $failedArchived;
	}

	/**
	 * uptade/create the unified lines in the DB.
	 * @return the unified lines that were failed to be updaed/created in the DB.
	 */
	public function updateUnifiedLines() {
		Billrun_Factory::log('Updating ' . count($this->unifiedLines) . ' unified lines...', Zend_Log::INFO);
		$db = Billrun_Factory::db();
		$db->setMongoNativeLong(1);
		$updateFailedLines = array();
		foreach ($this->unifiedLines as $key => $row) {
			$query = array('stamp' => $key, 'type' => $row['type'], 'tx' => array('$nin' => $this->unifiedToRawLines[$key]['update']));
			$base_update = array(
				'$setOnInsert' => array(
					'stamp' => $key,
					'source' => 'unify',
					'type' => $row['type'],
//					'billrun' => $this->activeBillrun,
			));
			$update = array_merge($base_update, $this->getlockLinesUpdate($this->unifiedToRawLines[$key]['update']));
			foreach ($this->unificationFields[$row['type']]['fields'] as $fkey => $fields) {
                if(method_exists($this, $fkey) ) {
                    $this->{$fkey}($row,$fields,$update);
                } else {
                    foreach ($fields as $field) {
                        if (isset($row[$field])) {
                            $update[$fkey][$field] = $row[$field];
                        }
                    }
                }
			}
			$update['$inc']['lcount'] = $row['lcount'];

			$linesCollection = $db->linesCollection();
			try {
				$ret = $linesCollection->update($query, $update, array('w' => 1, 'upsert' => true));
			} catch (Exception $e) {
				// if it's duplicate let's retry to unify again (only once)
				if ($e->getCode() == 11000) {
					Billrun_Factory::log("Duplicate line raised when trying to unify into line " . $key, Zend_Log::WARN);
					usleep(1000);
					$ret = $linesCollection->update($query, $update, array('w' => 1, 'upsert' => true));
				} else {
					throw $e;
				}
			}
			$success = isset($ret['ok']) && $ret['ok'] && isset($ret['n']) && $ret['n'] > 0;
			if (!$success) {//TODO add support for w => 0 it should  not  enter the if
				$updateFailedLines[$key] = array('unified' => $row, 'lines' => $this->unifiedToRawLines[$key]['update']);
				foreach ($this->unifiedToRawLines[$key]['update'] as $lstamp) {
					unset($this->archivedLines[$lstamp]);
				}
				Billrun_Factory::log("Updating unified line $key failed.", Zend_Log::ERR);
			}
		}
		$db->setMongoNativeLong(0);
		return $updateFailedLines;
	}

	public function write() {
		// update db.lines don't update the queue if  a given line failed.
		foreach ($this->updateUnifiedLines() as $failedLine) {
			foreach ($failedLine['lines'] as $stamp) {
				unset($this->lines[$stamp]);
			}
		}
		//add lines to archive 
		$this->saveLinesToArchive();

		parent::write();
	}

	/**
	 * Get or create a unified row from a given single row
	 * @param string $updatedRowStamp the unified stamp that the returned row should have.
	 * @param array $newRow the single row.
	 * @return array containing  a new or existing unified row.
	 */
	protected function getUnifiedRowForSingleRow($updatedRowStamp, $newRow) {
		$type = $newRow['type'];
		if (isset($this->unifiedLines[$updatedRowStamp])) {
			$existingRow = $this->unifiedLines[$updatedRowStamp];
			foreach ($this->unificationFields[$type]['fields']['$inc'] as $field) {
				if (isset($newRow[$field]) && !isset($existingRow[$field])) {
					$existingRow[$field] = 0;
				}
			}
		} else {
			//Billrun_Factory::log(print_r($newRow,1),Zend_Log::ERR);
			$existingRow = array('lcount' => 0, 'type' => $type);
			foreach ($this->unificationFields[$type]['fields'] as $key => $fields) {
                if(method_exists($this, $key) ) {
                    continue;
                } else {
                    foreach ($fields as $field) {
                        if ($key == '$inc' && isset($newRow[$field])) {
                            $existingRow[$field] = 0;
                        } else if (isset($newRow[$field])) {
                            $existingRow[$field] = $newRow[$field];
                        } else {
                            //Billrun_Factory::log("Missing Field $field for row {$newRow['stamp']} when trying to unify.", Zend_Log::DEBUG);
                        }
                    }
                }
			}
		}

		return $existingRow;
	}

	/**
	 * Get the unified row stamp for a given single line.
	 * @param type $newRow the single line to extract the unified row stamp from.
	 * @return a string  with the unified row stamp.
	 */
	protected function getLineUnifiedLineStamp($newRow) {
		$typeData = $this->unificationFields[$newRow['type']];
		$serialize_array = array();
		foreach ($typeData['stamp']['value'] as $field) {
			if (isset($newRow[$field])) {
				$serialize_array[$field] = $newRow[$field];
			}
		}

		foreach ($typeData['stamp']['field'] as $field) {
			$serialize_array['exists'][$field] = isset($newRow[$field]) ? '1' : '0';
		}
		$dateSeperation = (isset($typeData['date_seperation']) ? $typeData['date_seperation'] : $this->dateSeperation);
		$serialize_array['dateSeperation'] = date($dateSeperation, $newRow['urt']->sec);
		return Billrun_Util::generateArrayStamp($serialize_array);
	}

	public function isLineLegitimate($line) {
		$matched = $line['source'] != 'unify' && isset($this->unificationFields[$line['type']]);

		if ($matched) {
			$requirements = $this->unificationFields[$line['type']]['required'];
			foreach ($requirements['match'] as $field => $regex) {
				// @todo: make it pluginable with chain of responsibility
				if ($field == 'classMethod') {
					$matched = call_user_func_array(array($this, $regex), array($line));
				} elseif (!preg_match($regex, $line[$field])) {
					$matched = false;
				}
			}
		}
		return $matched &&
				//verify that all the required field exists in the line
				(count(array_intersect(array_keys($line->getRawData()), $requirements['fields'])) == count($requirements['fields']));
	}

	public function isNsnLineLegitimate($line) {
		if ((isset($line['arate']) && $line['arate'] !== false) || (isset($line['usaget']) && (isset($line['sid']))) ||
			(in_array($line['out_circuit_group'], array('2090','2091','2092')) || in_array($line['in_circuit_group'], array('2090','2091','2092')))) {
			return false;
		}
		return true;
	}

	/**
	 * 
	 * @param type $unifiedStamp
	 * @param type $lineStamps
	 * @return type
	 */
	protected function isLinesLocked($unifiedStamp, $lineStamps) {
		$query = array('stamp' => $unifiedStamp, 'tx' => array('$in' => $lineStamps));
		return !Billrun_Factory::db()->linesCollection()->query($query)->cursor()->limit(1)->current()->isEmpty();
	}

	/**
	 * Check if certain lines already inserted to the archive.
	 * @param type $lineStamps an array containing the line stamps to check
	 * @return boolean true if the line all ready exist in the archive false otherwise.
	 */
	protected function isLinesArchived($lineStamps) {

		return !$this->archiveDb->linesCollection()->query(array('stamp' => array('$in' => $lineStamps)))->cursor()->limit(1)->current()->isEmpty();
	}

	/**
	 * Get the update argument/query to lock lines for a unfied line in a the DB.
	 * @param type $lineStamps the stamps of the lines to lock.
	 * @return array update query to pass on to an update  action.
	 */
	protected function getlockLinesUpdate($lineStamps) {
		$txarr = array();
		foreach ($lineStamps as $value) {
			$txarr[$value] = true;
		}
		$update = array('$pushAll' => array('tx' => $lineStamps));
		return $update;
	}

	/**
	 * Release lock for given lines in a unified line in the DB.
	 * @param type $unifiedStamp the unified line stamp to release the single line on.
	 * @param type $lineStamps the stamp of the single lines to release from lock.
	 */
	protected function releaseLines($unifiedStamp, $lineStamps) {
		$query = array('stamp' => $unifiedStamp);

		$update = array('$pullAll' => array('tx' => $lineStamps));
		Billrun_Factory::db()->linesCollection()->update($query, $update, array('w' => 1));
	}

	/**
	 * 
	 */
	public function releaseAllLines() {
		Billrun_Factory::log('Removing locks from  ' . count($this->unifiedToRawLines) . ' unified lines...', Zend_Log::INFO);
		foreach ($this->unifiedToRawLines as $key => $value) {
			$this->releaseLines($key, $value['remove']);
		}
	}

	/**
	 * 
	 * @return type
	 */
	protected function getLines() {
		$types = array('ggsn', 'smpp', 'mmsc', 'smsc', 'nsn', 'tap3', 'credit');
		return $this->getQueuedLines(array('type' => array('$in' => $types)));
	}

	/**
	 * 
	 * @return string
	 */
	public function getCalculatorQueueType() {
		return 'unify';
	}

	public function removeFromQueue() {
		parent::removeFromQueue();
		$this->releaseAllLines();
	}
	
	///////////////////////  Helper functions //////////////////
	
	protected function _array_map($row,$mappings,&$update) {
        foreach($mappings as $fieldMapping) {
            if( empty($row[$fieldMapping['field']]) ) {
                continue;
            }
            foreach($row[$fieldMapping['field']] as $arrItem) {
                $key='';
                foreach($fieldMapping['keys'] as $idKeyField) {
                    $key .= $arrItem[$idKeyField]; 
                }
                foreach($fieldMapping['values'] as $fkey => $fields) {
                    foreach($fields as $field) {
                        if (isset($arrItem[$field])) {
							$toField = empty($fieldMapping['to_field']) ? $fieldMapping['field'] : $fieldMapping['to_field'];
                            $update[$fkey][$toField.'.'.$key.'.'.$field] = $arrItem[$field];
                        }
                    }
                }
            }
        }

        return $update;
	}
	
	protected function _array_map_new($row,$mappings,$updateRow) {
        foreach($mappings as $fieldMapping) {
            if( isset($row[$fieldMapping['field']]) ) {
                $updateRow[$fieldMapping['field']] = $row[$fieldMapping['field']];
            }
        }
        return $updateRow;
	}

}
