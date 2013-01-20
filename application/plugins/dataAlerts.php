<?php

class dataAlertsPlugin extends Billrun_Plugin_BillrunPluginBase {
		/**
	 * plugin name
	 *
	 * @var string
	 */
	protected $name = 'dataAlerts';
	
	protected static $alertServer = "http://127.0.0.1";

	public function thresholdReached($alertDispather, $args) {
		
		if(	!isset($args['type']) || 
			$args['type'] != 'ggsn' || 
			!isset($args['thresholdType'])) { 
			return FALSE; 
		}
		$type = $args['thresholdType'];
		switch($type) {
			case 'upload' :
				$args['threshold'] = $args['threshold'] / 1024;
				$args['value'] = $args['value'] / 1024;
				$args['units'] = "KB";
				$this->notifyOnEvent($args);
				break;
			case 'download' :
				$args['threshold'] = $args['threshold'] / 1024;
				$args['value'] = $args['value'] / 1024;
				$args['units'] = "KB";
				$this->notifyOnEvent($args);
				break;
			case 'duration' :
				$args['units'] = "SEC";
				$this->notifyOnEvent($args);
				break;
		}
		$event = new Mongodloid_Entity();
		$event->setRawData(array('type' => $args['type'],'event_type' => $args['thresholdType'],'value'=>$args['value'], "imsi" => $args['imsi'] , "msisdn" => $args['msisdn'], 'stamp' => $args['stamp'] ));
		$this->db->getCollection(static::events_table)->save($event);
	}
	
	protected function notifyOnEvent($args) {
		Billrun_Log::getInstance()->log("EgsnAlertcdPlugin::notifyOnEvent {$args['imsi']} with type : {$args['thresholdType']} , value : {$args['value']}", Zend_LOg::DEBUG);	
		$client = curl_init(static::$alertServer."/?event_type=GGSN_DATA&IMSI={$args['imsi']}".
														"&NDC_SN=". preg_replace("/^19972/","",$args['msisdn']).
														"&threshold={$args['threshold']}".
														"&usage={$args['value']}".
														"&units={$args['units']}" );
		curl_setopt($client, CURLOPT_POST, TRUE);
		curl_setopt($client, CURLOPT_POSTFIELDS, array('extra_data' => json_encode($args)));
		curl_exec($client);
		curl_close($client);
	}
}