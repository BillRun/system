<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billapi model for balance update by prepaid include
 *
 * @package  Billapi
 * @since    5.3
 */
class Billrun_Balance_Update_Secret extends Billrun_Balance_Update_Chargingplan {

	/**
	 * the update method type
	 * @var string
	 */
	protected $updateType = 'Secret';

	/**
	 * card details container
	 * 
	 * @var array
	 */
	protected $card;

	public function __construct(array $params = array()) {
		if (isset($params['secret'])) {
			$this->secret = (string) $params['secret'];
			unset($params['secret']); // security reason
		} else {
			throw new Billrun_Exceptions_Api(0, array(), 'Secret not provide');
		}
		$this->card = $this->getCardBySecret((string) $this->secret);
		if ($this->card->isEmpty()) {
			throw new Billrun_Exceptions_Api(0, array(), 'Card not found');
		}
		$params['charging_plan_name'] = $this->card['charging_plan_name'];
		parent::__construct($params);
	}

	/**
	 * load the card details by secret
	 * 
	 * @param string $secret secret not hashed
	 * 
	 * @return Mongodloid_Entity card details
	 */
	protected function getCardBySecret($secret) {
		$dateQuery = Billrun_Utils_Mongo::getDateBoundQuery();
		$secretQuery = array(
			"secret" => hash('sha512', $secret),
		);
		$finalQuery = array_merge($dateQuery, $secretQuery);
		$finalQuery['status'] = array('$eq' => 'Active');
		$cardsColl = Billrun_Factory::db()->cardsCollection();
		return $cardsColl->query($finalQuery)->cursor()->current();
	}

	/**
	 * @todo
	 */
	public function update() {
		if (parent::update() === false) {
			return false;
		}
		$this->signalCardAsUsed();
		return true;
	}

	/**
	 * method to mark card as used
	 * run only after success charge
	 */
	protected function signalCardAsUsed() {
		$query = array(
			'_id' => array(
				'$eq' => $this->card['_id']->getMongoID()
			), // next fields added because of the sharding (cluster env)
			'batch_number' => $this->card['batch_number'],
			'serial_number' => $this->card['serial_number'],
		);
		$update = array(
			'$set' => array(
				'status' => 'Used',
				'sid' => $this->subscriber['sid'],
				'activation_datetime' => new MongoDate(),
			),
		);
		$options = array(
			'upsert' => false,
		);
		$cardsColl = Billrun_Factory::db()->cardsCollection();
		return $cardsColl->findAndModify($query, $update, array(), $options, true);
	}

	/**
	 * method to track change in audit trail
	 * 
	 * @return true on success log change else false
	 * @todo track the card entity change
	 */
	protected function trackChanges() {
		parent::trackChanges();
	}

}
