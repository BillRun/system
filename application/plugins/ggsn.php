<?php

class ggsnPlugin extends Billrun_Plugin_BillrunPluginBase {

	
	/**
	 * plugin name
	 *
	 * @var string
	 */
	protected $name = 'ggsn';

	
	public function handlerAlert(&$items,$pluginName) {
		if($pluginName != $this->getName()) {return;}
		
		$db = Billrun_Factory::db();
		$events = $db->getCollection($db::events_table);
		//$this->log->log("New Alert For {$item['imsi']}",Zend_Log::DEBUG);
		$ret = array();
		foreach($items as &$item) { 
			$newEvent = new Mongodloid_Entity($item);
			unset($newEvent['lines_ids']);
			$newEvent['source']='ggsn';
			$newEvent['stamp'] = md5(serialize($newEvent));
			$item['alert_stamp'] = $newEvent['stamp'];
			$ret[] = $events->save($newEvent);
		}
		return $ret; 
	}
	
	public function handlerMarkDown(&$items, $pluginName) {
		if($pluginName != $this->getName()) {return;}
		//$this->log->log("Marking down Alert For {$item['imsi']}",Zend_Log::DEBUG);
		$ret = array();
		$db = Billrun_Factory::db();
		$lines = $db->getCollection($db::lines_table);
		foreach($items as &$item) { 
			$ret[] = $lines->update(	array('stamp'=> array('$in' => $item['lines_ids'])),
								array('$set' => array('alert_stamp' => $item['alert_stamp'])),
								array('multiple'=>1));
		}
		return $ret;
	}
	
	/**
	 * method to collect data which need to be handle by event
	 */
	public function handlerCollect() {
		$db = Billrun_Factory::db();
		$lines = $db->getCollection($db::lines_table);
		$charge_time = $this->get_last_charge_time();

		$aggregateQuery = array(
			array(
				'$match' => array(
					'type' => 'egsn',
					'sgsn_address' => array('$regex' => '^(?!62\.90\.|37\.26\.)'),
					'record_opening_time' => array('$gte' => $charge_time),
					'deposit_stamp' => array('$exists' => false),
					'alert_stamp' => array('$exists' => false),
				),
			),
			array(
				'$group' => array(
					"_id" => array('imsi'=>'$served_imsi','msisdn' =>'$served_msisdn'),
					"download" => array('$sum' => '$fbc_downlink_volume'),
					"upload" => array('$sum' => '$fbc_uplink_volume'),
					"duration" => array('$sum' => '$duration'),
					'lines_ids' => array('$addToSet' => '$stamp'),
				),	
			),
			array(
				'$project' => array(
					'_id' => 0,
					'download' => array('$multiply' => array('$download',0.001)),
					'upload' => array('$multiply' => array('$download',0.001)),
					'duration' => 1,
					'imsi' => '$_id.imsi',
					'msisdn' => array('$substr'=> array('$_id.msisdn',5,10)),
					'lines_ids' => 1,
				),
			),
		);
		$alerts = array();
		foreach( $this->detectDataExceeders($lines, $aggregateQuery) as $alert) {
			$alerts[] = $alert;
		}
		$this->log->log("Found ". count($alerts) . " Exceeders",Zend_Log::DEBUG);
		
		return $alerts;
	}

	protected function detectDataExceeders($lines,$aggregateQuery) {
		$limit = floatval($this->getConfigValue('ggsn.thresholds.datalimit',1000));
		$dataThrs =	array(
				'$match' => array(
					'$or' => array(
							array( 'download' => array( '$gte' => $limit ) ),
							array( 'upload' => array( '$gte' => $limit ) ),		
					),
				),
			);
		$dataAlerts = $lines->aggregate(array_merge($aggregateQuery, array($dataThrs)) );
		foreach($dataAlerts as &$alert) {
			$alert['units'] = 'KB';
			$alert['value'] = ($alert['download'] > $limit ? $alert['download'] : $alert['upload']);
			$alert['threshold'] = $limit;
			$alert['alert_type'] = 'data';
		}
		return $dataAlerts;
	}
	
	protected function detectDurationExceeders($lines,$aggregateQuery) {
		$threshold = floatval($this->getConfigValue('ggsn.thresholds.duration',2400));
		$durationThrs =	array(
				'$match' => array(
					'duration' => array('$gte' => $threshold )
				),
			);
		$durationAlert = $lines->aggregate(array_merge($aggregateQuery, array($durationThrs)) );
		foreach($durationAlert as &$alert) {
			$alert['units'] = 'SEC';
			$alert['value'] = $alert['duration'];
			$alert['threshold'] = $threshold;
			$alert['alert_type'] = 'data_duration';
		}
		return $durationAlert;
	}
	
	protected function get_last_charge_time($return_timestamp = false) {
		// TODO take the 25 from config
		$dayofmonth = $this->getConfigValue('billrun.charging_day',25);
		$format = "Ym" . $dayofmonth . "000000";
        if (date("d") >= $dayofmonth) {
            $time = date($format);
        } else {
            $time = date($format, strtotime('-1 month'));
        }
        if ($return_timestamp) {
            return strtotime($time);
        }
        return $time;
    }
}