<?php

require_once 'ilds.php';

/**
 * override class of ilds. it will trigger same event to premium (done by the name property trick)
 */
class premiumPlugin extends ildsPlugin {

	/**
	 * plugin name
	 *
	 * @var string
	 */
	protected $name = 'premium';
	
	protected $fraud_event_name = 'FP_NATIONAL_1_PREMIUM';


}