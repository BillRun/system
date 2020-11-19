<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

require_once APPLICATION_PATH . '/application/controllers/Action/Api.php';

class AccountInvoicesAction extends ApiAction {
	use Billrun_Traits_Api_UserPermissions;
	
	public function execute() {
		$this->allowed();
		$request = $this->getRequest();
		try {
			
			switch ($request->get('action')) {
				case 'query' :
						$retValue = $this->queryIvoices($request->get('query'));	
					break;
				case 'download':
					$retValue = $this->downloadPDF($request);
					break;
				case 'expected_invoice':
					$retValue = $this->generateExpectedInvoices($request);
					break;
				case 'search' :
				default :
					$retValue = $this->searchIvoicesForAid($request);
			}

			$this->getController()->setOutput(array(array(
					'status' => 1,
					'desc' => 'success',
					'details' => $retValue,
					'input' => $request->getRequest()
			)));
		} catch (Exception $ex) {
			Billrun_Factory::log('AccountInvoices Error: ' . print_r(array('input' => $request->getPost(), 'error'=> $ex->getMessage(), 'trace' => $ex->getTraceAsString()),1),Zend_Log::ERR);
			$this->setError($ex->getMessage(), $request->getPost());
			return;
		}
	}
	
	public function searchIvoicesForAid($request) {
		$aid = $request->get('aid');
		$months_back = intval($request->get('months_back'));
		$billrun_keys = array();
		$billrun_keys[0] = Billrun_Billingcycle::getBillrunKeyByTimestamp();
		for ($i = 1; $i <= $months_back; $i++) {
			$billrun_keys[$i] = Billrun_Billingcycle::getPreviousBillrunKey($billrun_keys[$i - 1]);
		}

		$params = array(
			"aid" => intval($aid),
			"billrun_key" => array('$in' => $billrun_keys)
		);

		$db = Billrun_Factory::db();
		$result = $db->billrunCollection()->query($params);
		$retValue = array();
		foreach ($result as $key => $value) {
			$retValue[$key] = $value->getRawData();
		}
		
		return $retValue;
	}

	protected function downloadPDF($request) {
		$aid = $request->get('aid');
		$confirmedOnly = $request->get('confirmed_only');
		$billrun_key = $request->get('billrun_key');
		$invoiceId = $request->get('iid');
		
		$query = array(
			'aid' => (int) $aid,
			'billrun_key' => $billrun_key
		);
		if (!empty($invoiceId)) {
			$query['invoice_id'] = (int) $invoiceId;
		}
		$invoice = Billrun_Factory::db()->billrunCollection()->query($query)->cursor()->current();
		if ($invoice->isEmpty()) {
			return 'Invoice was not found';
		}
		if (!is_null($confirmedOnly) && $confirmedOnly == '1') {
			if (empty($invoice->get('billed')) || $invoice->get('billed') !== 1) {
				return 'Invoice not found';
			}
		}
		$invoiceId = $invoice['invoice_id'];
		$invoiceData = $invoice->getRawData();

		$file_name =  !empty($invoiceData['file_name']) ? $invoiceData['file_name'] : (!empty($invoiceData['invoice_file']) ? basename($invoiceData['invoice_file']) : $billrun_key . '_' . $aid . '_' . $invoiceId . ".pdf");
		$pdf = $invoiceData['invoice_file'];

		if( $request->get('detailed') ) {
			$generator = Billrun_Generator::getInstance(array('type'=>'wkpdf','accounts'=>array((int)$aid),'subscription_details'=>1,'usage_details'=> 1,'stamp'=>$billrun_key));
			$generator->load();
			$generator->generate();
		}
		if (!file_exists($pdf)){
                        Billrun_Factory::log('Invoice file ' . $pdf . ' does not exist', Zend_Log::NOTICE);
			echo "Invoice not found";
		} else {
			$cont = file_get_contents($pdf);
			if ($cont) {
				header('Content-disposition: inline; filename="'.$file_name.'"');
				header('Cache-Control: public, must-revalidate, max-age=0');
				header('Pragma: public');
				header('Last-Modified: '.gmdate('D, d M Y H:i:s').' GMT');
				header('Content-Type: application/pdf');
				Billrun_Factory::log('Transfering invoice content from : '.$pdf .' to http connection');
				echo $cont;
			} 
		}
		die();
	}
	
	protected function generateExpectedInvoices($request) {
		$params = $request->getRequest();
		$options = array(
			'type' => 'expectedinvoice',
			'aid' => $params['aid'],
			'stamp' => $params['billrun_key'],
		);
		$generator = Billrun_Generator::getInstance($options);
		$generator->load();
		$pdfPath = $generator->generate();
		$cont = file_get_contents($pdfPath);
		if ($cont) {
			header('Content-disposition: inline; filename="'.$file_name.'"');
			header('Cache-Control: public, must-revalidate, max-age=0');
			header('Pragma: public');
			header('Last-Modified: '.gmdate('D, d M Y H:i:s').' GMT');
			header('Content-Type: application/pdf');
			Billrun_Factory::log('Transfering invoice content from : '.$pdfPath .' to http connection');
			echo $cont;
			die();
		}  else {
			$this->setError('Failed when trying to generate expected invoice' , $request->getPost());
			Billrun_Factory::log('Failed when trying to generate expected invoice to : '.$pdfPath );
		}

	}
	
	protected function queryIvoices($query, $sort = FALSE) {
		$billrunColl = Billrun_Factory::db()->billrunCollection();
		Billrun_Plan::getCacheItems();
		$q = json_decode($query, JSON_OBJECT_AS_ARRAY);
		if (is_array($q['creation_date'])) {
			$q['creation_date'] = $this->intToMongoDate($q['creation_date']);
		}
		$invoices = $billrunColl->query($q)->cursor()->setRawReturn(true);
		if($sort) {
			$invoices->sort($sort);
		}
		$retValue = array();
		foreach ($invoices as $key => $invoice) {
			if(empty($invoice['subs'])) {
				continue;
			}
			
			foreach ($invoice['subs'] as &$service) {
				$service['next_plan'] = empty($service['next_plan']) ? Billrun_Util::getFieldVal($service['next_plan'], null) : Billrun_Plan::getPlanById(strval($service['next_plan']['$id']))['key'];
				$service['current_plan'] = empty($service['current_plan']) ? Billrun_Util::getFieldVal($service['current_plan'], null) : Billrun_Plan::getPlanById(strval($service['current_plan']['$id']))['key'];
			}
			
			$retValue[$key] = $invoice;
		}
		return $retValue;
	}
	
	protected function intToMongoDate($arr) {
		if (is_array($arr)) {
			foreach ($arr as $key => $value) {
				if (is_numeric($value)) {
					$arr[$key] = new MongoDate((int) $value);
				}
			}
		} else if (is_numeric($arr)) {
			$arr = new MongoDate((int) $arr);
		}
		return $arr;
	}

	protected function getPermissionLevel() {
		return Billrun_Traits_Api_IUserPermissions::PERMISSION_READ;
	}

}
