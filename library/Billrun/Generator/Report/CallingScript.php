<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Billing call generator class
 * Make and  receive call  base on several  parameters
 *
 * @package  Billing
 * @since    0.5
 */
class Billrun_Generator_Report_CallingScript extends Billrun_Generator_Report {

	const TYPE_REGULAR = 'regular';
	const TYPE_BUSY = 'busy';
	const TYPE_VOICE_MAIL = 'voice_mail';
	const TYPE_NO_ANSWER = 'no_answer';
	
	// The minimoum time to wait between calls  in seconds.
	const MINIMUM_TIME_BETWEEN_CALLS = 60;
	
	// The time to  wait  between concecative script type in seconds.
	const SCRIPT_TYPES_SEPERATION = 900;
	
	const REGULAR_CALL_RATE = 0.00254237;
	
	const CALLEE = 'callee';
	const CALLER = 'caller';
	const VOIVE_MAIL_DURATION = 5;
	const NO_ANSWER_DURATION = 10;

	const CONCURRENT_CONFIG_ENTRIES = 50;
	
	/**
	 * The script to generate calls by.
	 */
	protected $testId = "test";

	/**
	 * The test identification.
	 */
	protected $scriptType = self::TYPE_REGULAR;
	
	/**
	 *
	 * @var type 
	 */
	protected $numbers = array("0586325444","0586792924"); 
	
	/**
	 *
	 * @var type 
	 */
	protected $activeDays = array(0,1,2,3,4,5,6); 
	
	/**
	 *
	 * @var type 
	 */
	protected $durations = array(10=>0.2,15=>0.2,35=>0.2,80=>0.2,180=>0.2);
	
	protected $voiceMailNumber = "0547371030";
	protected $busyNumber = "0547371030";
	
	public function __construct($options) {
		parent::__construct($options);
		if (isset($options['script_type'])) {
			$this->scriptType = is_array($options['script_type']) ? $options['script_type'] : explode(",",$options['script_type']);
		}
		if (isset($options['numbers'])) {
			$this->numbers = is_array($options['numbers']) ? $options['numbers'] : explode(",",$options['numbers']);
		}		
		if (isset($options['durations'])) {
			$this->durations = is_array($options['durations']) ? $options['durations'] : explode(",",$options['durations']);
		}
		
		if (isset($options['active_days'])) {
			$this->activeDays = is_array($options['active_days']) ? $options['active_days'] : explode(",",$options['active_days']);
		}
		
		if (isset($options['start_test'])) {
			$this->startTestAt = strtotime($options['start_test']);
		}
		
		if (isset($options['voice_mail_number'])) {
			$this->voiceMailNumber = $options['voice_mail_number'];
		}
		
		if (isset($options['busy_number'])) {
			$this->busyNumber = $options['busy_number'];
		}
		
	}

	/**
	 * Generate the calls as defined in the configuration.
	 */
	public function generate() {
		$types = array(	'regular'=> array( 'daily' => 420, 'total_count' => 18900 , 'rate' => self::REGULAR_CALL_RATE),
						'busy' => array( 'daily' => 100, 'total_count' => 100, 'rate' => 0) ,
						'no_answer' => array( 'daily' => 100, 'total_count' => 100, 'rate' => 0) ,
						'voice_mail' => array( 'daily' => 50, 'total_count' => 50, 'rate' => 0));
		$aggDaily = 0;
		$aggCount = 0;
		
		foreach ($this->scriptType as $type) {
			if(!isset($types[$type])) {
				Billrun_Factory::log("The call type {$type} that was  defined in ". join(",",$this->scriptType)." isn't a legal type.");
				return false;
			}
			$aggCount += $types[$type]['total_count']; //* Billrun_Util::getFieldVal($this->options['fail_pecentage_multiplier'],1);
			$aggDaily += $types[$type]['daily'];
		}
		$durations =  $this->generateDurationsDivision($this->durations,$aggDaily);

		$options =array(
												'script_type' => $this->scriptType,
												'numbers' => $this->numbers,
												'durations' => $durations,
												'types' => $types,
												'daily_start_time' => isset($this->startTestAt) ? date('H:i:s',$this->startTestAt) : '00:10:00',
											);
		if(isset($this->options['total_calls_count'])) {
			$options['total_calls_count'] = $this->options['total_calls_count'];
			$aggCount = $this->options['total_calls_count'];
		}
		$actions = $this->generateDailyScript($options);

		$startDay = strtotime( date('Ymd H:i:s',isset($this->startTestAt) ? $this->startTestAt: time()) );
		$endDay = strtotime( date('Ymd H:i:s',$startDay+(86400 * ( $aggCount / $aggDaily ) * (7 / max(count($this->activeDays),7)) ) ) );
		$config = array('actions' => $actions , 'test_id' => $this->testId."_".time()  , 'from' => $startDay , 'to' => $endDay , 'call_count' => $aggCount,  'active_days' =>  $this->activeDays ,'state'=> 'start');
		if($actions) {
			if(!empty($this->options['to_remote'])) {
				foreach ($this->options['generator']['remote_servers_url'] as $host) {
					$this->updateRemoteCallGen($config, $host);
				}
			}

			if(isset($this->options['out']) && $this->options['out']) {
				$this->generateFiles(array(join("_",$this->scriptType).'.json.dump' => $config), $this->options['out']);
			}
		}
		if(Billrun_Util::getFieldVal($this->options['update_config'],false)) {
			$this->updateConfig($config);
		}
		return $config;
	}	
	
	/**
	 * Load the script
	 */
	public function load() {

	}
	
	
	/**
	 * 
	 * @param type $config
	 * @param type $host
	 * @return type
	 */
	public function updateRemoteCallGen($config,$host) {
		
		$client = new Zend_Http_Client($host);
		$config['test_script'] = $config['actions'];
		unset($config['actions']);
		$client->setParameterPost( array( 'data'  => json_encode($config) ) );
		$response = $client->request('POST');
		
		return $response;
	}
	/**
	 * Ceate a repetative daily script run.
	 * @param type $params
	 * @return type
	 */
	public function generateDailyScript($params) {
		$startOffset = $offset =(int) strtotime($params['daily_start_time']) % 86400 ;
		
		$actions = array();
		$sides = array(self::CALLEE, self::CALLER);
		$callId = 0;
		$callsCount = 0; 
		$numbersCount = count($params['numbers']);
		foreach($params['script_type'] as  $scriptType  ) {
			if(isset($params['total_calls_count']) && $params['total_calls_count'] < $callsCount) {
				break;
			}
			$typeData = $params['types'][$scriptType];
			for($i = 0; $i < $typeData['daily']; $i++) {
			  $action = array();
			  $action['time'] = date('H:i:s',$offset);
			  $action['from'] = $params['numbers'][($i/$numbersCount) % $numbersCount];
			  $numbersToCall = array_values(array_diff($params['numbers'], array($action['from'])));			  
			  $action['to'] =  ( $scriptType == 'voice_mail' ? $this->voiceMailNumber : $numbersToCall[ $i % ($numbersCount-1)]);

			  if($scriptType == 'busy') {
				  $action['busy_number'] = $numbersCount <= 2 ? $this->busyNumber : $numbersToCall[(1 + $i) % ($numbersCount-1)];
			  }
			  $action['duration'] = ( $scriptType == self::TYPE_VOICE_MAIL ?  self::VOIVE_MAIL_DURATION :
										($scriptType == self::TYPE_NO_ANSWER ? self::NO_ANSWER_DURATION  :
										$params['durations'][$i %  count($params['durations'])] ) );

			  $action['hangup'] = ($scriptType == self::TYPE_VOICE_MAIL ? self::CALLER : $sides[$i % count($sides)]);
			  $action['action_type'] = $scriptType;
			  $action['rate'] = $typeData['rate'];
			  $action['call_id'] = $callId++;

			 $offset = 60 * ceil( ($offset + $action['duration'] + self::MINIMUM_TIME_BETWEEN_CALLS) / 60 );
			 $actions[] = $action;
			 $callsCount++;
			}
			$offset = 60 * ceil( ($offset + self::SCRIPT_TYPES_SEPERATION + self::MINIMUM_TIME_BETWEEN_CALLS) / 60 );
			if($offset - $startOffset > 86400) {
				Billrun_Factory::Log('The script must fit into a 24 hours cycle. please seperate the  test scripts  to different tests. '.$offset);
				return false;
			}
		}
		
		return $actions;
	}
	
	/**
	 * @see Billrun_Generator_Report::writeToFile( &$fd, &$report )
	 */
	function writeToFile( &$fd, &$report ) {
			$urt = $report['from'] > time() ? $report['from'] : time(); 
			fwrite($fd, "db.config.insert({key:'call_generator',urt:ISODate('". date('Y-m-d H:i:sP',$urt)."'),");
			fwrite($fd, "from:ISODate('". date('Y-m-d H:i:sP',$report['from']) ."'),");
			fwrite($fd, "to:ISODate('". date('Y-m-d H:i:sP',$report['to']) ."'),");
			fwrite($fd, "test_id :'{$report['test_id']}'," );
			fwrite($fd, "test_script : \n" );
			fwrite($fd, json_encode($report['actions'],JSON_PRETTY_PRINT));
			fwrite($fd, "\n});" );
	}
		
	
	public function updateConfig($data) {
		Billrun_Factory::log()->log("Updating Config", Zend_Log::INFO);
		$data['test_script'] = $data['actions'];
		unset($data['actions']);
		$data['key'] = 'call_generator';
		$data['urt'] = new MongoDate($data['from'] > time() ? $data['from'] : time()); 
		$data['from'] = new MongoDate($data['from']);
		$data['to'] = new MongoDate($data['to']);
		$configCol = Billrun_Factory::db()->configCollection();		
		$entity = new Mongodloid_Entity($data,$configCol);		
		
		if ($entity->isEmpty() || $entity->save($configCol) === false) {
			Billrun_Factory::log()->log('Failed to store configuration into DB',Zend_Log::ALERT);
			return false;
		}
		
		$this->removeOldEnteries($entity['key'],$data['from'],$data['to'],$data['urt']);
		
		Billrun_Factory::log()->log("Updated Config", Zend_Log::INFO);
		return true;
	}
	
	/**
	 * generate a call durations array  where the  duration a divided by  a given percentage.
	 * @param type $durations an array  conatining the durations  and the  precentage  of them to apper.
	 * @param type $dailyCallsCount the  tootal  calls  that will be done  per day.
	 * @return array with randomaly distributaed calls
	 */
	protected function generateDurationsDivision( $durations, $dailyCallsCount ) {
		$dividedDurations = array();
		foreach($durations as $dur => $precent ) {
			$durations[$dur] = ($dailyCallsCount * $precent);
		}		
		while( count($dividedDurations) < $dailyCallsCount )
		foreach ($durations as $dur => &$value) {		
			if($value-- >= 0) {
				$dividedDurations[] = $dur;
			}
		}
		return $dividedDurations;
	}


	/**
	 * Remove old config entries
	 * @param type $keythe  key to remove old entries for.
	 */
	protected function removeOldEnteries($key,$from , $to, $urt) {
		$oldEntries = Billrun_Factory::db()->configCollection()->query(array('key' => $key))->cursor()->sort(array('urt'=>-1))->skip(static::CONCURRENT_CONFIG_ENTRIES);
		foreach ($oldEntries as $entry) {
			$entry->collection(Billrun_Factory::db()->configCollection());
			$entry->remove();
		}
		
		return Billrun_Factory::db()->configCollection()->remove(array('key' => $key, 'from' => array('$gte' => $from, '$lte' => $to),'urt' => array('$lt'=> $urt)));
		
	}
}
