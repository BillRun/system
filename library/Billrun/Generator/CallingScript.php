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

	/**
	 * The script to generate calls by.
	 */
	protected $testId = "test";

	/**
	 * The test identification.
	 */
	protected $scriptType = self::TYPE_REGULAR;
	
	protected $numbers = array("0586325444","0586792924"); //'',
	
	
	public function __construct($options) {
		parent::__construct($options);
		if (isset($options['script_type'])) {
			$this->scriptType = $options['script_type'];
		}
		if (isset($options['numbers'])) {
			$this->numbers = split(",",$options['numbers']);
		}
	}

	/**
	 * Generate the calls as defined in the configuration.
	 */
	public function generate() {
		$durations = array(10,15,35,80,180);
		$types = array('regular'=> 420,'busy' => 250 ,'no_answer' => 250 ,'voice_mail' => 250);
		
		$sides = array('callee','caller');
		
		if(!isset($types[$this->scriptType])) {
			Billrun_Factory::log("The call type {$this->scriptType} isn't a legal type.");
			return false;
		}
		
		$offset = 60;
		$actions = array();
		$numbersCont = count($this->numbers);
		for($i = 0; $i < $types[$this->scriptType]; $i++) {
		  $action = array();
		  $action['time'] = date('H:i:s',$offset);
		  $action['from'] = $this->numbers[($i/$numbersCont) % $numbersCont];
		  $action['to'] = $this->numbers[(1+ $i - ($i/$numbersCont)) % $numbersCont];
		  $action['duration'] = ( $this->scriptType == 'voice_mail' ?  1.5  : $durations[$i %  count($durations)] );
		  $action['hangup'] = $sides[$i % count($sides)];
		  $action['action_type'] = $this->scriptType;

		 $offset = 60 * ceil( ($offset + $action['duration']+self::MINIMUM_TIME_BETWEEN_CALLS) / 60 );
		 $actions[] = $action;
		}

		return array("{$this->scriptType}.json.dump" => array('actions' => $actions , 'test_id' => $this->testId));
	}

	public function getTemplate($param= null) {
		return 'json_dump.json.phtml';
	}
	
	/**
	 * Load the script
	 */
	public function load() {

	}

}
