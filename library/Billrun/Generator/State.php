<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Billing abstract generator ilds class
 * require to generate xml for each account
 * require to generate csv contain how much to credit each account
 *
 * @package  Billing
 * @since    0.5
 */
class Billrun_Generator_State extends Billrun_Generator {
	protected $state = false;
	
	
	
	public function generate() {
		Billrun_Factory::log()->log("Execute State Action", Zend_Log::INFO);
		
		switch($this->options['action']) {		
			case 'stop':
				$this->stop($data);
				break;
			case 'start':
				$this->start($data);
				break;
			
		}
		Billrun_Factory::log()->log("Executed State Action", Zend_Log::INFO);
		return true;
	}

	public function load() {
		
	}
	
	/**
	 * 
	 * @param type $param
	 */
	public function stop($param = false) {
		$configCol = Billrun_Factory::db()->configCollection();
		$configCol->update(array('$query' => array('key'=>'call_generator','from'=> array('$lt'=> new MongoDate(time())) ),'$orderby'=>  array('urt'=> -1)),array('$set'=>array('state'=> 'stop','urt' => new MongoDate(time()))));
	}
	
	/**
	 * 
	 * @param type $param
	 */
	public function start($param = false) {
		$configCol = Billrun_Factory::db()->configCollection();
		$configCol->update(array('$query' => array('key'=>'call_generator','from'=> array('$lt'=> new MongoDate(time())) ),'$orderby'=>  array('urt'=> -1)),array('$set'=>array('state'=> 'start','urt' => new MongoDate(time()))));
	}
}
