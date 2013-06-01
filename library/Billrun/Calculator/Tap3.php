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
class Billrun_Calculator_Tap3 extends Billrun_Calculator {
	/**
	 * the type of the object
	 *
	 * @var string
	 */
	static protected $type = 'data';	

	/**
	 * method to get calculator lines
	 */
	protected function getLines() {
		$lines = Billrun_Factory::db()->linesCollection();
		
		return $lines->query()
			->in('type', array('tap3'))
			->notExists('customer_rate')->cursor()->limit($this->limit);

	}

	/**
	 * write the calculation into DB.
	 * @param $row the line CDR to update. 
	 */
	protected function updateRow($row) {
		Billrun_Factory::dispatcher()->trigger('beforeCalculatorWriteRow', array('row' => $row));
		$header = $this->getLineHeader($row);
		$current = $row->getRawData();
		$location_information = $row['LocationInformation'];
		if($location_information !== FALSE ) {
			
		}
		
		
		
		$record_type = $row->get('record_type');
		$called_number = $row->get('called_number');
		$ocg = $row->get('out_circuit_group');
		$icg = $row->get('in_circuit_group');

		$rates = Billrun_Factory::db()->ratesCollection();

		if ($record_type == "01" || ($record_type == "11" && ($icg == "1001" || $icg == "1006" || ($icg > "1201" && $icg < "1209")))) {
			$called_number_prefixes = $this->getPrefixes($called_number);

			$base_match = array(
				'$match' => array(
					'params.prefix' => array(
						'$in' => $called_number_prefixes,
					),
					'call' => array('$exists' => true ),
					'params.out_circuit_group' => array(
						'$elemMatch' => array(
							'from' => array(
								'$lte' => $ocg,
							),
							'to' => array(
								'$gte' => $ocg
							)
						)
					)
				)
			);

			$unwind = array(
				'$unwind' => '$params.prefix',
			);

			$sort = array(
				'$sort' => array(
					'params.prefix' => -1,
				),
			);

			$match2 = array(
				'$match' => array(
					'params.prefix' => array(
						'$in' => $called_number_prefixes,
					),
				)
			);

			$matched_rates = $rates->aggregate($base_match, $unwind, $sort, $match2);

		}

		if (!empty($matched_rates)) {
			$rate = reset($matched_rates);
			$current = $row->getRawData();
			$rate_reference = array(
				'customer_rate' => $rate['_id'],
			);
			$newData = array_merge($current, $rate_reference);
			$row->setRawData($newData);
		}
		
		Billrun_Factory::dispatcher()->trigger('afterCalculatorWriteRow', array('row' => $row));
	}

	
	/**
	 * Get the header data  of the file that a given TAP3 CDR line belongs to. 
	 * @param type $line the cdr  lline to get the header for.
	 * @return Object representing the file header of the line.
	 */
	protected function getLineHeader($line) {
		return Billrun_Factory::db()->logCollection()->query(array('header.stamp'=> $line['header_stamp']))->cursor()->current();
	}
	
}

?>
