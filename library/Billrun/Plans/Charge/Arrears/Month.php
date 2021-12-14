<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Calculates a monthly charge
 *
 * @package  Plans
 * @since    5.2
 */
class Billrun_Plans_Charge_Arrears_Month extends Billrun_Plans_Charge_Base {

	protected $isTerminated = FALSE;

	public function __construct($plan) {
		parent::__construct($plan);
		$this->setMonthlyCover();
	}

	/**
	 * Get the price of the current plan.
	 */
	public function getPrice($quantity = 1) {

		$charges = array();
		foreach ($this->price as $tariff) {
			$price = $this->getTariffForMonthCover($tariff, $this->startOffset, $this->endOffset, $this->activation);
			if (!empty($price)) {
				$prorationData = $this->getProrationData($price);
				$charges[] = array_merge(['value' => $price['price'] * $quantity,
					'cycle' => $tariff['from'],
					'full_price' => floatval($tariff['price'])],
						$prorationData);
			}
		}
		return $charges;
	}

	/**
	 * Get the price of the current plan.
	 */
	protected function setMonthlyCover() {
		$formatActivation = $this->proratedStart ?
				date(Billrun_Base::base_dateformat, $this->activation) :
				date(Billrun_Base::base_dateformat, Billrun_Billingcycle::getBillrunStartTimeByDate(date(Billrun_Base::base_dateformat, $this->activation)));

		$formatStart = date(Billrun_Base::base_dateformat, strtotime('-1 day', $this->cycle->start()));
		$fakeSubDeactivation = (empty($this->subscriberDeactivation) ? PHP_INT_MAX : $this->subscriberDeactivation);
		$this->isTerminated = ($fakeSubDeactivation <= $this->deactivation || empty($this->deactivation) && $fakeSubDeactivation < $this->cycle->end());
		$adjustedDeactivation = (empty($this->deactivation) || (!$this->proratedEnd && !$this->isTerminated || !$this->proratedTermination && $this->isTerminated ) ? $this->cycle->end() : $this->deactivation - 1);
		$formatEnd = date(Billrun_Base::base_dateformat, min($adjustedDeactivation, $this->cycle->end() - 1));

		$this->startOffset = Billrun_Utils_Time::getMonthsDiff($formatActivation, $formatStart);
		$this->endOffset = Billrun_Utils_Time::getMonthsDiff($formatActivation, $formatEnd);
	}

	/**
	 *
	 */
	protected function getTariffForMonthCover($tariff, $startOffset, $endOffset, $activation = FALSE) {
		return Billrun_Plan::getPriceByTariff($tariff, $startOffset, $endOffset, $activation);
	}

	protected function getProrationData($price) {
		$endProration = $this->proratedEnd && !$this->isTerminated || ($this->proratedTermination && $this->isTerminated);
		$proratedActivation = $this->proratedStart || $this->startOffset ? $this->activation : $this->cycle->start();
		$proratedEnding = $this->cycle->end() >= $this->deactivation ? $this->deactivation : FALSE;
		return ['start_date' => new Mongodloid_Date(Billrun_Plan::monthDiffToDate($price['start'], $this->activation)),
			'start' => $this->proratedStart ? Billrun_Plan::monthDiffToDate($price['start'], $proratedActivation) : $this->cycle->start(),
			'prorated_start_date' => new Mongodloid_Date($this->proratedStart && $this->activation > $this->cycle->start() ? Billrun_Plan::monthDiffToDate($price['start'], $proratedActivation) : $this->cycle->start()),
			'prorated_start' => $this->proratedStart,
			'end' => $endProration ? Billrun_Plan::monthDiffToDate($price['end'], $proratedActivation, FALSE, $proratedEnding, $this->deactivation && $this->cycle->end() > $this->deactivation) : $this->cycle->end(),
			'prorated_end_date' => new Mongodloid_Date($endProration && $this->cycle->end() > $this->deactivation ? Billrun_Plan::monthDiffToDate($price['end'], $proratedActivation, FALSE, $proratedEnding, $this->deactivation && $this->cycle->end() > $this->deactivation) : $this->cycle->end()),
			'end_date' => new Mongodloid_Date(Billrun_Plan::monthDiffToDate($price['end'], $this->activation, FALSE, $this->deactivation, $this->deactivation && $this->cycle->end() > $this->deactivation)),
			'prorated_end' => $endProration
		];
	}

}
