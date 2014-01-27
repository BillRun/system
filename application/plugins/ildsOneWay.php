<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Calculator ildsOneWay plugin create csv file for ilds one way process
 *
 * @package  Application
 * @subpackage Plugins
 * @since    0.9
 */
class ildsOneWayPlugin extends Billrun_Plugin_BillrunPluginBase {

	/**
	 * plugin name
	 *
	 * @var string
	 */
	protected $name = 'ildsOneWay';

	public function afterProcessorStore($processor) {
            
                $str = '';
                $data = $processor->getData();
		Billrun_Factory::log('Plugin ildsOneWay', Zend_Log::INFO);
		foreach ($data['data'] as $line) {
			$entity = new Mongodloid_Entity($line);
			$line = $entity->getRawData();    
			$record_types = Billrun_Factory::config()->getConfigValue('016.one_way.identifications');

			if(in_array($line['record_type'], $record_types['record_type']) && preg_match($record_types['called_number'] ,substr($line['called_number'], 0, 6))) {

					$result = $this->createRow($line);
					if(!empty($result)) {
						$str .= $result;

						Billrun_Factory::log()->log("line stamp: ". $line['stamp'] ." file: ". $line['file'] ." was inserted to 016 one way process", Zend_Log::INFO);
					}
			}
		}
                
                if(!empty($str)) {
                    $create_file = $this->createTreatedFile($str);
                    
                    if(!$create_file) {
                        Billrun_Factory::log()->log("failed creating 016 one way file, time: ".time(), Zend_Log::ERR);
                    }
                    
                    Billrun_Factory::log()->log("016 one way file ware created with the name: ".$create_file, Zend_Log::INFO);
                }
	}
        
        /**
	 * @see Billrun_Generator_Csv::createRow
	 */
        public function createRow($row) {

			$providers = array('992016' => '013', '993016' => 'ILD_BEZ', '994016' => '019', '995016' => '012', '997016' => 'ILD_HOT');
			
			$row['file'] = 'cdrFile:'. $row['file'] .' cdrNb:'.$row['record_number'];
			$row['charging_start_time'] = substr($row['charging_start_time'], 2);
			$row['charging_end_time'] = substr($row['charging_end_time'], 2);                                
			$row['prepaid'] = '0';
			$row['is_in_glti'] = '0';
			$row['origin_carrier'] = $providers[substr($row['called_number'], 0, 6)];
			$row['records_type'] = '000';
			$row['sampleDurationInSec'] = '1';

			$row['aprice'] = '1.4468'; // HACK ONLY FOR CHECKING !! REMOVE IT ON PRODUCTION -----------------------------------

			if($row['usagev'] == '0') {
				$row['records_type'] = '005';
			}
			else if(!$row['arate']) {
				$row['records_type'] = '001';
				$row['sampleDurationInSec'] = '0';
			}
								
            $str = '';
            $data_structure = $this->dataStructure();
            foreach ($data_structure as $column => $width) {
                $str .= str_pad($row[$column],$width," ",STR_PAD_LEFT);
            }
            
            $str .= PHP_EOL;     
            return $str;
        }
                
        /*
         * return the data structure
         */
        public function dataStructure() {
            return array(
                    'records_type' => 3,
                    'calling_number' => 15,
                    'charging_start_time' => 13,
                    'charging_end_time' => 13,
                    'called_number' => 18,
                    'is_in_glti' => 1,
                    'prepaid' => 1,
                    'usagev' => 10,
                    'sampleDurationInSec' => 8,
                    'aprice' => 10,
                    'origin_carrier' => 10,
                    'file' => 100,
               );
        }
        
        /**
	 * @see Billrun_Generator_Csv::createTreatedFile
	 */
        public function createTreatedFile($str) {
                $fileName = date('Ymd', time());
                
		$path = Billrun_Factory::config()->getConfigValue('016_one_way.export.path') . '/' . $fileName . '.TXT';
                
		if (file_put_contents($path, $str)) {
                    return $fileName;
                }
                
                Billrun_Factory::log()->log("cannot put content of file: ". $fileName . "path: ". $path, Zend_Log::ERR);
                return FALSE;
	}
}
