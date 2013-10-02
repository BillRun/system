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


	/**
	 * The script to generate calls by.
	 */
	protected $testId = "test";

	/**
	 * The test identification.
	 */
	protected $scriptType = self::TYPE_REGULAR;
	

	public function __construct($options) {
		parent::__construct($options);
		if (isset($options['script_type'])) {
			$this->scriptType = $options['script_type'];
		}
	}

	/**
	 * Generate the calls as defined in the configuration.
	 */
	public function generate() {
		$durations = array(10,15,35,80,180);
		$types = array('regular','busy','no_answer','voice_mail');
		$numbers = array('0586792924','0547371030');
		$sides = array('callee','caller');

		$offset = 60;
		$actions = array();
		for($i = 0; $i < 420; $i++) {
		  $action = array();
		  $action['time'] = date('H:i:s',$offset);
		  $action['from'] = $numbers[($i/count($numbers)) % count($numbers)];
		  $action['to'] = $numbers[(1+ $i - ($i/count($numbers))) % count($numbers)];
		  $action['duration'] = ( $this->scriptType == 'voice_mail' ?  1.5  : $durations[$i %  count($durations)] );
		  $action['hangup'] = $sides[$i % count($sides)];
		  $action['action_type'] = $this->scriptType;

		 $offset = 60 * ceil( ($offset + $action['duration']) / 60 );
		 $actions[] = $action;
		}

		return array("{$this->scriptType}.json.dump" => array('actions' => $actions , 'test_id' => $this->testId));
	}

	public function getTemplate($param= null) {
		return 'json_dump.phtml';
	}
	
	/**
	 * Load the script
	 */
	public function load() {

	}

}
