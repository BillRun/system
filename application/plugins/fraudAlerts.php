<?php
class fraudAlertsPlugin extends Billrun_Plugin_BillrunPluginBase {

	
	/**
	 * plugin name
	 *
	 * @var string
	 */
	protected $name = 'fraudAlerts';
		
	protected $alertHost = "http://127.0.0.1";
	
	public function __construct($options = array()) {
		parent::__construct($options);
		
		$this->alertServer = isset($options['alertHost']) ?
									$options['alertHost'] :
									$this->getConfigValue('fraudAlerts.alert.host', $this->alertHost);
	}
	
	public function handlerNotify() {
		$db = Billrun_Factory::db();
		$eventsCol = $db->getCollection($db::events_table);
		$lineCol = $db->getCollection($db::lines_table);
		
		$ret = $this->roamingNotify($db, $eventsCol, $lineCol);
		
		return $ret;
	}
	
	protected function roamingNotify($db, $eventsCol, $linesCol) {
		$retValue = array();
		$events = $eventsCol->aggregate(array('$match' => array('notify_time'=> array('$exists'=>false),
																/*'source'=> array('$in'=> array('nrtrde','ggsn'))*/)),
										array('$sort' => array('imsi' => 1)),
										array('$group' => array(
											'_id' => '$imsi',
											'id' => array('$addToSet'=> '$_id'),
											'imsi' => array('$first'=> '$imsi'),
											'value' => array('$first'=> '$value'),
											'alert_type' => array('$first'=> '$alert_type'),
											'units' => array('$first'=> '$units'),
											'msisdn' => array('$first'=> '$msisdn'),
											'threshold' => array('$first'=> '$threshold'),
										),));
		
		foreach($events as $event) {
			if($this->notifyOnEvent($event)) {
					Billrun_Log::getInstance()->log("handlerNotify ".print_r($event,1), Zend_LOg::DEBUG);
				//mark events has delt with.
				$eventsCol->update(	array( 'notify_time'=> array('$exists' => false),
											'_id' => array('$in' => $event['id']), 
										),
								array('$set' => array('notify_time' => time() )),
								array('multiple'=> 1));
				
				//mark deposit for the lines on the current imsi 
				$linesCol->update(	array( 'process_time'=> array('$lt'=> date('Y-m-d H:i:s',time()),'imsi' => $event['imsi']) ),
								array('$set' => array('deposit_stamp' => $event['id'] )),
								array('multiple'=>1));
				$retValue[] = $event;
			}
		}
		return $retValue;
	}
	/**
	 * Atually send events to the remote server.
	 * @param type $args 
	 * @return type
	 */
	protected function notifyOnEvent($args) {
		$excedingValue = ( $args['value']);
		Billrun_Log::getInstance()->log("notifyOnEvent {$args['imsi']} with type : {$args['alert_type']} , value : {$excedingValue}", Zend_LOg::DEBUG);	
		$client = curl_init($this->alertServer."?event_type=GGSN_DATA&IMSI={$args['imsi']}".
														"&NDC_SN={$args['msisdn']}".
														"&threshold={$args['threshold']}".
														"&usage={$excedingValue}".
														"&units={$args['units']}" );
		curl_setopt($client, CURLOPT_POST, TRUE);
		curl_setopt($client, CURLOPT_POSTFIELDS, array('extra_data' => json_encode($args)));
		curl_setopt($client, CURLOPT_RETURNTRANSFER, TRUE);
		$response = curl_exec($client);
		curl_close($client);
		
		Billrun_Log::getInstance()->log("EgsnAlertcdPlugin::notifyOnEvent ".print_r(json_decode($response), 1), Zend_LOg::DEBUG);			

		return json_decode($response);
	}
}