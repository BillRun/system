<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of Tap3
 *
 * @author eran
 */
class Billrun_Calculator_Tap3 extends Billrun_Calculator_Base_Rate {

	/**
	 * the type of the object
	 *
	 * @var string
	 */
	static protected $type = 'tap3';

	/**
	 * method to get calculator lines
	 */
	protected function getLines() {
		$lines = Billrun_Factory::db()->linesCollection();

		return $lines->query()
				->in('type', array(static::$type))
				->notExists('customer_rate')->cursor()->limit($this->limit);
	}

	/**
	 * write the calculation into DB.
	 * @param $row the line CDR to update. 
	 */
	protected function updateRow($row) {
		Billrun_Factory::dispatcher()->trigger('beforeCalculatorWriteRow', array('row' => $row));

		$current = $row->getRawData();
		
		$volume = $this->getLineVolume($row);
		$rate = $this->getLineRate($row);

		$added_values = array(
			'usage_v' => $volume,
			'customer_rate' => ($rate !== FALSE ? $rate->getMongoID() : $rate),
		);
		$newData = array_merge($current, $added_values);
		$row->setRawData($newData);

		Billrun_Factory::dispatcher()->trigger('afterCalculatorWriteRow', array('row' => $row));
	}

	protected function getLineVolume($row) {
		$volume = null;
		$usage_type = $row['usaget'];
		switch ($usage_type) {
			case 'sms' :
			case 'incoming_sms' :
				$volume = 1;
				break;

			case 'call' :
			case 'incoming_call' :
				$volume = $row->get('basicCallInformation.TotalCallEventDuration');
				break;

			case 'data' :
				$volume = $row->get('GprsServiceUsed.DataVolumeIncoming') + $row->get('GprsServiceUsed.DataVolumeOutgoing');
				break;
		}
		return $volume;
	}

	protected function getLineRate($row) {
		$header = $this->getLineHeader($row);
		$rates = Billrun_Factory::db()->ratesCollection();
		$log = Billrun_Factory::db()->logCollection();
		$line_time = $row['unified_record_time'];

		if (isset($row['LocationInformation']['GeographicalLocation']['ServingNetwork'])) {
			$serving_network = $row['LocationInformation']['GeographicalLocation']['ServingNetwork'];
		} else {
			$serving_network = $log->query(array('source' => static::$type, 'header.stamp' => $row['header_stamp']))->cursor()->current()->get('header.data.header.sending_source');
		}

		if (!is_null($serving_network)) {
			$rates = Billrun_Factory::db()->ratesCollection();

			if (isset($row['usaget'])) {
				$filter_array = array(
					'params.serving_networks' => array(
						'$in' => array($serving_network),
					),
					'rates.' . $row['usaget'] => array(
						'$exists' => true,
					),
					'from' => array(
						'$lte' => $line_time,
					),
					'to' => array(
						'$gte' => $line_time,
					),
				);
				$rate = $rates->query($filter_array)->cursor()->current();
				if ($rate->getId()) {
					return $rate->get('_id');
				}
			}
		}

		return false;
	}

	/**
	 * Get the header data  of the file that a given TAP3 CDR line belongs to. 
	 * @param type $line the cdr  lline to get the header for.
	 * @return Object representing the file header of the line.
	 */
	protected function getLineHeader($line) {
		return Billrun_Factory::db()->logCollection()->query(array('header.stamp' => $line['header_stamp']))->cursor()->current();
	}

}

?>
