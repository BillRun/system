<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2020 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

require_once APPLICATION_PATH . '/application/controllers/Action/Api.php';

/**
 * Taxation action class
 */
class ChargesAction extends ApiAction {
	
	use Billrun_Traits_Api_UserPermissions;	
	
	public function execute() {
		$this->allowed();
		$request = $this->getRequest()->getRequest();
		$data = isset($request['data']) ? json_decode($request['data'], JSON_OBJECT_AS_ARRAY) : [];
		$ret = [];
		if (empty($request['operation'])) {
			$request['operation'] = 'calculate';
		}
		
		switch ($request['operation']) {
			case 'calculate':
				$ret = $this->calculateCharges($data);
				break;
			default:
				return $this->setError('Unrecognized operation', $request);
		}
		
		return $this->setSuccess($ret);
	}
	
	protected function calculateCharges($data) {
		$taxCalc = Billrun_Calculator::getInstance(['type' => 'tax']);
		$line = $this->prepareLine($data);
		$subscriber = isset($data['subscriber']) ? $data['subscriber'] : [];
		$account = isset($data['account']) ? $data['account'] : [];
		$params = [
			'pretend' => true,
		];
		$line = $taxCalc->updateRowTaxInforamtion($line, $subscriber, $account, $params);
		$taxData = isset($line['tax_data']) ? $line['tax_data'] : [];
		if (empty($taxData)) {
			return $this->setError('Failed to get tax data', $data);
		}
		
		$ret = [
			'price' => $line['aprice'],
			'tax' => $taxData['total_tax'],
			'tax_amount' => $taxData['total_amount'],
			'final_price' => $line['aprice'] + $taxData['total_amount'],
		];
		
		if (!empty($data['include_tax_data'])) {
			$ret['tax_data'] = $taxData;
		}
		
		return $ret;
	}
	
	protected function prepareLine($data) {
		$line = [];
		
		if (!empty($data['rate'])) {
			$rate = Billrun_Rates_Util::getRateByName($data['rate']);
			$usaget = array_keys($rate['rates'])[0];
			$line['type'] = 'credit';
			$line['arate'] = $rate->createRef(Billrun_Factory::db()->ratesCollection());
			$line['aprice'] = isset($data['aprice']) ? $data['aprice'] : Billrun_Rates_Util::getTotalCharge($rate, $usaget, 1);
		} else if (!empty($data['plan'])) {
			$line['type'] = 'flat';
			$line['name'] = $data['plan'];
			$plan = new Billrun_Plan(['name' => $data['plan'], 'time'=> time()]);
			$line['aprice'] = $this->getPrice($plan);
		} else if (!empty($data['service'])) {
			$line['type'] = 'service';
			$line['name'] = $data['service'];
			$service = new Billrun_Service(['name' => $data['service']]);
			$line['aprice'] = $this->getPrice($service);
		} else {
			return $this->setError('Charges can be calculated on one of: "rate"/"plan"/"service"');
		}
		
		$line['stamp'] = Billrun_Util::generateArrayStamp($line);
		$line['urt'] = new MongoDate();
		return $line;
	}
	
	protected function getPrice($service) {
		$price = $service->get('price');
		return Billrun_Util::getIn($price, '0.price', 0);
	}
	
	protected function getPermissionLevel() {
		return Billrun_Traits_Api_IUserPermissions::PERMISSION_ADMIN;
	}

}
