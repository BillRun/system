<?php

class ggsnAlertsPlugin extends Billrun_Plugin_BillrunPluginBase {

	
	/**
	 * plugin name
	 *
	 * @var string
	 */
	protected $name = 'ggsnAlerts';

	public function handlerAlert(&$item,$pluginName) {
		if($pluginName != $this->getName()) {return;}
		
		$db = Billrun_Factory::db();
		$events = $db->getCollection($db::events_table);
		//$this->log->log("New Alert For {$item['imsi']}",Zend_Log::DEBUG);
		
		$newEvent = new Mongodloid_Entity($item);
		unset($newEvent['lines_ids']);
		$newEvent['stamp'] = md5(serialize($newEvent));
		$item['alert_stamp'] = $newEvent['stamp'];
		return $events->save($newEvent);
	}
	
	public function handlerMarkDown(&$item, $pluginName) {
		if($pluginName != $this->getName()) {return;}
		//$this->log->log("Marking down Alert For {$item['imsi']}",Zend_Log::DEBUG);
		
		$db = Billrun_Factory::db();
		$lines = $db->getCollection($db::lines_table);
		return $lines->update(	array('stamp'=> array('$in' => $item['lines_ids'])),
								array('$set' => array('alert_stamp' => $item['alert_stamp'])),
								array('multiple'=>1));

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
		$dataThrs =	array(
				'$match' => array(
					'$or' => array(
							array( 'download' => array( '$gte' => floatval($this->config->ggsn->thresholds->datalimit)) ),
							array( 'upload' => array( '$gte' => floatval($this->config->ggsn->thresholds->datalimit)) ),		
					),
				),
			);
		$dataAlerts = $lines->aggregate(array_merge($aggregateQuery, array($dataThrs)) );
		foreach($dataAlerts as &$alert) {
			$alert['units'] = 'KB';
			$alert['threshold'] = $this->config->ggsn->thresholds->datalimit;
			$alert['alert_type'] = 'data';
		}
		return $dataAlerts;
	}
	
	protected function detectDurationExceeders($lines,$aggregateQuery) {
		$durationThrs =	array(
				'$match' => array(
					'duration' => array('$gte' => floatval($this->config->ggsn->thresholds->duration) )
				),
			);
		$durationAlert = $lines->aggregate(array_merge($aggregateQuery, array($durationThrs)) );
		foreach($durationAlert as &$alert) {
			$alert['units'] = 'SEC';
			$alert['threshold'] = $this->config->ggsn->thresholds->duration;
			$alert['alert_type'] = 'data_duration';
		}
		return $durationAlert;
	}
	
	protected function notifyOnEvent($args) {
		Billrun_Log::getInstance()->log("EgsnAlertcdPlugin::notifyOnEvent {$args['imsi']} with type : {$args['thresholdType']} , value : {$args['value']}", Zend_LOg::DEBUG);	
		$client = curl_init(static::$alertServer."?event_type=GGSN_DATA&IMSI={$args['imsi']}".
														"&NDC_SN={$args['msisdn']}".
														"&threshold={$args['threshold']}".
														"&usage={$args['value']}".
														"&units={$args['units']}" );
		curl_setopt($client, CURLOPT_POST, TRUE);
		curl_setopt($client, CURLOPT_POSTFIELDS, array('extra_data' => json_encode($args)));
		curl_setopt($client, CURLOPT_RETURNTRANSFER, TRUE);
		$response = curl_exec($client);
		curl_close($client);
//		Billrun_Log::getInstance()->log("EgsnAlertcdPlugin::notifyOnEvent {$args['imsi']} API  response : $response", Zend_LOg::DEBUG);	
		return json_decode($response);
	}
	
		protected function get_last_charge_time($return_timestamp = false) {
		// TODO take the 25 from config
		$dayofmonth = $this->config->billrun->charging_day;
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