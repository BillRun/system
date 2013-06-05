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
		$rate = $this->getLineRate($row);
		$added_values = array(
			'customer_rate' => ($rate !== FALSE ? $rate->getMongoID() : $rate),
		);
		$newData = array_merge($current, $added_values);
		$row->setRawData($newData);

		Billrun_Factory::dispatcher()->trigger('afterCalculatorWriteRow', array('row' => $row));
	}

	protected function getLineRate($row) {
		$header = $this->getLineHeader($row);
		$int_network_mappings = Billrun_Factory::db()->intnetworkmappingsCollection();
		$log = Billrun_Factory::db()->logCollection();

		if (isset($row['LocationInformation']['GeographicalLocation']['ServingNetwork'])) {
			$serving_network = $row['LocationInformation']['GeographicalLocation']['ServingNetwork'];
		} else {
			$serving_network = $log->query(array('source' => static::$type, 'header.stamp' => $row['header_stamp']))->cursor()->current()->get('header.data.header.sending_source');
		}

		if (!is_null($serving_network)) {
			$rates = Billrun_Factory::db()->ratesCollection();

			if (isset($row['usage_type'])) {
				$rate_key = $int_network_mappings->query(array('PLMN' => $serving_network))->cursor()->current()->get('type.' . $row['usage_type']);
				if (!is_null($rate_key)) {
					$rate = $rates->query(array('key' => $rate_key))->cursor()->current();
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
