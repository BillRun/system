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
class Billrun_Generator_CallingScript extends Billrun_Generator {

	const TYPE_REGULAR = 'regular';
	const TYPE_BUSY = 'busy';
	const TYPE_VOICE_MAIL = 'voice_mail';
	const TYPE_NO_ANSWER = 'no_answer';
	
	const MINIMUM_TIME_BETWEEN_CALLS = 10;
	
	const CALLEE = 'callee';
	const CALLER = 'caller';
	const VOIVE_MAIL_DURATION = 1.5;
	const NO_ANSWER_DURATION = 10;

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
	protected $durations = array(10,15,35,80,180);
	
	protected $voiceMailNumber = "0547371030";
	protected $busyNumber = "0547371030";
	
	public function __construct($options) {
		parent::__construct($options);
		if (isset($options['script_type'])) {
			$this->scriptType = $options['script_type'];
		}
		if (isset($options['numbers'])) {
			$this->numbers = split(",",$options['numbers']);
		}		
		if (isset($options['durations'])) {
			$this->numbers = split(",",$options['durations']);
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
		$types = array(	'regular'=> array( 'daily' => 420, 'total_count' => 18900 , 'rate' => 0.015),
						'busy' => array( 'daily' => 100, 'total_count' => 100, 'rate' => 0) ,
						'no_answer' => array( 'daily' => 100, 'total_count' => 100, 'rate' => 0) ,
						'voice_mail' => array( 'daily' => 50, 'total_count' => 50, 'rate' => 0));
		
		if(!isset($types[$this->scriptType])) {
			Billrun_Factory::log("The call type {$this->scriptType} isn't a legal type.");
			return false;
		}
		
		$actions = $this->generateDailyScript(array(
												'script_type' => $this->scriptType,
												'numbers' => $this->numbers,
												'durations' => $this->durations,
												'types' => $types,
												'daily_start_time' => '12:00:00',
											));

		$startDay = strtotime(date('Ymd 00:00:00'),isset($this->startTestAt) ? $this->startTestA : time());
		$endDay = strtotime(date('Ymd 00:00:00',$startDay+(86400 * ($types[$this->scriptType]['total_count'] / $types[$this->scriptType]['daily'] )) ) );
		$config = array('actions' => $actions , 'test_id' => $this->testId , 'from' => $startDay , 'to' => $endDay );
		
		if(!empty($this->options['to_remote'])) {
			foreach ($this->options['generator']['remote_servers_url'] as $host) {
				$this->updateRemoteCallGen($config, $host);
			}
		}
		
		if(isset($this->options['out']) && $this->options['out']) {
			$this->generateFiles(array("{$this->scriptType}.json.dump" => $config), $this->options['out']);
		}
		return $config;
	}	
	
	/**
	 * Load the script
	 */
	public function load() {

	}
	
	public function updateRemoteCallGen($config,$host) {
		
		$client = new Zend_Http_Client($host);
		$client->setParameterPost( array( 'data'  => json_encode($config) ) );
		$response = $client->request('POST');
		
		return $response;
	}
	
	public function generateDailyScript($params) {
		$offset =(int) strtotime($params['daily_start_time']) % 86400 ;
		$scriptType = $params['script_type'];
		$typeData = $params['types'][$scriptType];
		
		$actions = array();
		$sides = array(self::CALLEE, self::CALLER);
		$callId = 0;
		$numbersCont = count($params['numbers']);
		for($i = 0; $i < $typeData['daily']; $i++) {
		  $action = array();
		  $action['time'] = date('H:i:s',$offset);
		  $action['from'] = $params['numbers'][($i/$numbersCont) % $numbersCont];
		  $action['to'] =  ( $scriptType == 'voice_mail' ? $this->voiceMailNumber : $params['numbers'][(1+ $i - ($i/$numbersCont)) % $numbersCont]);
		  
		  if($scriptType == 'busy') {
			  $action['busy_number'] = $numbersCont <= 2 ? $this->busyNumber : $params['numbers'][(2+ $i - ($i/$numbersCont)) % $numbersCont];
		  }
		  $action['duration'] = ( $scriptType == self::TYPE_VOICE_MAIL ?  self::VOIVE_MAIL_DURATION :
									($scriptType == self::TYPE_NO_ANSWER ? self::NO_ANSWER_DURATION  :
									$params['durations'][$i %  count($params['durations'])] ) );
		  
		  $action['hangup'] = $sides[$i % count($sides)];
		  $action['action_type'] = $scriptType;
		  $action['rate'] = $typeData['rate'];
		  $action['call_id'] = $callId++;

		 $offset = 60 * ceil( ($offset + $action['duration']+self::MINIMUM_TIME_BETWEEN_CALLS) / 60 );
		 $actions[] = $action;
		 
		}
		
		return $actions;
	}

	
	/**
	 * 
	 * @param type $resultFiles
	 * @param type $generator
	 * @param type $outputDir
	 */
	protected function generateFiles($resultFiles,$outputDir = GenerateAction::GENERATOR_OUTPUT_DIR) {
		foreach ($resultFiles as $name => $report) {
			$fname = date('Ymd'). "_" . $name .".json";
			Billrun_Factory::log("Generating file $fname");
			$fd = fopen($outputDir. DIRECTORY_SEPARATOR.$fname,"w+");//@TODO change the  output  dir to be configurable.
			fwrite($fd, "db.config.insert({key:'call_generator',urt:ISODate('". date('Y-m-d\TH:i:s\Z')."'),");
			fwrite($fd, "from:ISODate('". date('Y-m-d\TH:i:s\Z',$report['from']) ."'),");
			fwrite($fd, "to:ISODate('". date('Y-m-d\TH:i:s\Z',$report['to']) ."'),");
			fwrite($fd, "test_id :'{$report['test_id']}'," );
			fwrite($fd, "test_script : [" );
			fwrite($fd, json_encode($report['actions']));
			fwrite($fd, "-]});" );
			fclose($fd);	
			}				
	}
}