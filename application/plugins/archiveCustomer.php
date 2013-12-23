<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Fraud deposit plugin
 *
 * @package  Application
 * @subpackage Plugins
 * @since    0.5
 */
class archiveCustomerPlugin extends Billrun_Plugin_BillrunPluginBase {
  
    	/**
	 * plugin name
	 *
	 * @var string
	 */
	protected $name = 'archiveCustomer';

        /**
	 * the container dataCustomer of the archive
	 * @var array
	 */
	protected $dataCustomer = array();
        
    	/**
	 * method to collect data which need to be handle by event
	 */
	public function handlerCollect($options) {
                if($this->getName() != $options['type']) { 
			return FALSE; 
		}
                
		Billrun_Factory::log()->log("Collect archive - customer line that older then 3 monthes", Zend_Log::INFO);
		$lines = Billrun_Factory::db()->linesCollection();
                
                $results = $lines->query(array(
					'urt' => array('$lte' => new MongoDate (strtotime('-3 months'))),
                                        'billrun' => array('$exists' => true, '$ne' => '000000'),
                ));
                                
		Billrun_Factory::log()->log("archive found " . $results->cursor()->count() . " lines", Zend_Log::INFO);
		return $results;
	}
        
        /**
	 * 
	 * @param type $items
	 * @param type $pluginName
	 * @return array
	 */
	public function handlerMarkDown(&$items, $pluginName) {
		if($pluginName != $this->getName() || !$items ) {return;}
                
                $archive = Billrun_Factory::db(Billrun_Factory::config()->getConfigValue('archive.db'))->linesCollection();
                
		Billrun_Factory::log()->log("Marking down archive lines For archive plugin",Zend_Log::INFO);
                
                $options = array();
                $this->dataCustomer = array();
		foreach($items as $item) {
                        $current = $item->getRawData();
                        $options['w'] = 1;
			$insertResult = $archive->insert($current, $options);
                        
                        if($insertResult['ok'] == 1) {
                            Billrun_Factory::log()->log("line with the stamp: " .$current['stamp']. " inserted to the archive",Zend_Log::INFO);
                            $this->dataCustomer[] = $current;
                        }
                        else {
                            Billrun_Factory::log()->log("Failed insert line with the stamp: " .$current['stamp']. " to the archive",Zend_Log::ERR);
                        }
		}
		return TRUE;
	}
        
        /**
	 * Handle Notification that should be done on events that were logged in the system.
	 * @param type $handler the caller handler.
	 * @return type
	 */
	public function handlerNotify($handler, $options) {
            if( $options['type'] != 'archiveCustomer') {
                return FALSE; 
            }
                
            if(!empty($this->dataCustomer)) {
                
                $lines = Billrun_Factory::db()->linesCollection();
                
                foreach ($this->dataCustomer as $item) {
                    $result = $lines->remove($item);
                }
            }
            return TRUE;
        }
	
}