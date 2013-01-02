<?php

/**
 * @category   Billrun
 * @package    Processor
 * @subpackage Nrtrde
 * @copyright  Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license    GNU General Public License version 2 or later
 */

/**
 * Billing processor for NRTRDE
 * see also:
 * http://www.tapeditor.com/OnlineDemo/NRTRDE-ASCII-format.html
 *
 * @package    Billing
 * @subpackage Processor
 * @since      1.0
 */
class Billrun_Processor_Type_Nrtrde extends Billrun_Processor_Separator {

	protected $type = 'nrtrde';

	public function __construct($options) {
		parent::__construct($options);
		
		$this->header_structure = array(
			'specificationVersionNumber',
			'releaseVersionNumber',
			'sender',
			'recipient',
			'sequenceNumber',
			'fileAvailableTimeStamp',
			'utcTimeOffset',
			'callEventsCount',
		);
		$this->moc_structure = array(
			'imsi',
			'imei',
			'callEventStartTimeStamp',
			'utcTimeOffset',
			'callEventDuration',
			'causeForTermination',
			'teleServiceCode',
			'bearerServiceCode',
			'supplementaryServiceCode',
			'dialledDigits',
			'connectedNumber',
			'thirdPartyNumber',
			'recEntityId',
			'callReference',
			'chargeAmount',
		);

		$this->mtc_structure = array(
			'imsi',
			'imei',
			'callEventStartTimeStamp',
			'utcTimeOffset',
			'callEventDuration',
			'causeForTermination',
			'teleServiceCode',
			'bearerServiceCode',
			'callingNumber',
			'recEntityId',
			'callReference',
			'chargeAmount',
		);
	}

}
