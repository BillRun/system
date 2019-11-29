<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of Tests_CustomerCalculator
 *
 * @author yossi
 */

require_once(APPLICATION_PATH . '/library/simpletest/autorun.php');

define('UNIT_TESTING', 'true');
      
class Tests_CustomerCalculator extends UnitTestCase {

	use Tests_SetUp;

    protected $message = '';
	protected $linesCol;
	protected $calculator;
	protected $fail = ' <span style="color:#ff3385; font-size: 80%;"> failed </span> <br>';
	protected $pass = ' <span style="color:#00cc99; font-size: 80%;"> passed </span> <br>';
	protected $rows = [
			array('row' => array('stamp' => '1', 'uf'=>['sid'=>2,'ndcsn'=>'123','date'=>'2019-11-27 10:39:00'],'urt'=>'2019-11-27 10:39:00',"usaget" =>"call","type" => "a","source"=>"a"),
			'expected' => array('aid'=>1,'sid'=>2,'plan'=>"PLAN_A",'firstname'=>'s','lastname'=>'s','services_data'=>[['name'=>'SERVICE_A','from'=>"2018-11-28T00:00:00","to"=>"2119-11-28T09:00:04",'service_id'=>'1651435539882454']])),
			array('row' => array('stamp' => '2', 'uf'=>['sid'=>3,'ndcsn'=>'972789','date'=>'2019-11-27 10:39:00'],'urt'=>'2019-11-27 10:39:00',"usaget" =>"call","type" => "a","source"=>"a"),
			'expected' => array('aid'=>1,'sid'=>3,'plan'=>"PLAN_A" ,'services'=>['SERVICE_A','SERVICE_B'],
				'firstname'=>'y','lastname'=>'y','services_data'=>[['name'=>'SERVICE_A','from'=>"2018-11-28T00:00:00","to"=>"2119-11-28T09:00:04",'service_id'=>'1651436461646613'],['name'=>'SERVICE_B','from'=>"2018-11-28T00:00:00","to"=>"2119-11-28T09:00:04",'service_id'=>'1651436461646736']]
				)),
		array('row' => array('stamp' => '3', 'uf'=>['sid'=>4,'ndcsn'=>'456','date'=>'2019-11-27 10:39:00'],'urt'=>'2019-11-27 10:39:00',"usaget" =>"call","type" => "a","source"=>"a"),
			'expected' => array('aid'=>1,'sid'=>4,'plan'=>"PLAN_B" ,'services'=>['SERVICE_A','SERVICE_B'],
				'firstname'=>'r','lastname'=>'r','services_data'=>[['name'=>'SERVICE_A','from'=>"2018-11-28T00:00:00","to"=>"2119-11-28T09:00:04",'service_id'=>'1651436572170505'],['name'=>'SERVICE_B','from'=>"2018-11-28T00:00:00","to"=>"2119-11-28T09:00:04"]]
				)),
		array('row' => array('stamp' => '4', 'uf'=>['sid'=>5,'ndcsn'=>'111','date'=>'2019-11-27 10:39:00'],'urt'=>'2019-11-27 10:39:00',"usaget" =>"call","type" => "a","source"=>"a"),
			'expected' => array('aid'=>1,'sid'=>5,'plan'=>"PLAN_A" ,'services'=>['SERVICE_A'],
				'firstname'=>'e','lastname'=>'e','services_data'=>[['name'=>'SERVICE_A','from'=>"2018-11-28T00:00:00","to"=>"2119-11-28T09:00:04",'service_id'=>'1651436618552622']]
				)),
		array('row' => array('stamp' => '5', 'uf'=>['sid'=>6,'ndcsn'=>'555','date'=>'2019-11-10 10:39:00'],'urt'=>'2019-11-10 10:39:00',"usaget" =>"call","type" => "a","source"=>"a"),
			'expected' => array('aid'=>1,'sid'=>6,'plan'=>"PLAN_A" ,'services'=>['SERVICE_A'],
				'firstname'=>'k','lastname'=>'k','services_data'=>[['name'=>'SERVICE_A','from'=>"2018-11-28T00:00:00","to"=>"2119-11-28T09:00:04",'service_id'=>'1651436728966341']]
				)),
		array('row' => array('stamp' => '6', 'uf'=>['sid'=>7,'ndcsn'=>'123456','date'=>'2019-11-10 10:39:00'],'urt'=>'2019-11-10 10:39:00',"usaget" =>"call","type" => "a","source"=>"a"),
			'expected' => array('aid'=>1,'sid'=>7,'plan'=>"PLAN_A" ,'firstname'=>'j','lastname'=>'j')),
		array('row' => array('stamp' => '7', 'uf'=>['sid'=>77,'ndcsn'=>'44123456','date'=>'2019-11-10 10:39:00'],'urt'=>'2019-11-10 10:39:00',"usaget" =>"call","type" => "a","source"=>"a"),
			'expected' => array('notExists'=>1 )),
	];

	public function __construct($label = 'customr calculator') {
		parent::__construct("test customer calculator");
		date_default_timezone_set('Asia/Jerusalem');
		$this->plansCol = Billrun_Factory::db()->plansCollection();
		$this->linesCol = Billrun_Factory::db()->linesCollection();
		$this->servicesCol = Billrun_Factory::db()->servicesCollection();
	    $this->construct(basename(__FILE__, '.php'), []);
		$this->setColletions();
		$this->loadDbConfig();
		$this->calculator = Billrun_Calculator::getInstance(array('type' => 'customer', 'autoload' => false));
	}

	public function loadDbConfig() {
		Billrun_Config::getInstance()->loadDbConfig();
	}

	public function TestPerform() {
		foreach ($this->rows as $key => $row) {
			$this->message .= 'test stamp : ' . $row['row']['stamp'];
			$fixrow = $this->fixRow($row['row'], $key);
			$this->linesCol->insert($fixrow);
			$updatedRow = $this->runT($fixrow['stamp']);
			$result = $this->compareExpected($key, $updatedRow, $row);
			$this->assertTrue($result);
			
			$this->message.='<p style="border-top: 1px dashed black;"></p>';
		}
		print ($this->message);
		$this->restoreColletions();
		
	}

	protected function runT($stamp) {
		$entity = $this->linesCol->query(array('stamp' => $stamp))->cursor()->current();
		$ret = $this->calculator->updateRow($entity);
		$this->calculator->writeLine($entity, '123');
		$entityAfter = $entity->getRawData();
		return ($entityAfter);
	}

	protected function compareExpected($key, $returnRow, $row) {
		$testFields['aid'] = isset($row['expected']['aid']) ? $row['expected']['aid'] : null;
		$testFields['sid'] = isset($row['expected']['sid']) ? $row['expected']['sid'] : null;
		$testFields['plan'] = isset($row['expected']['plan']) ? $row['expected']['plan'] : null;
		$testFields['firstname'] = isset($row['expected']['firstname']) ? $row['expected']['firstname'] : null;
		$testFields['lastname'] = isset($row['expected']['lastname']) ? $row['expected']['lastname'] : null;
		$services = isset($row['expected']['services']) ? $row['expected']['services'] : null;
		$services_data = isset($row['expected']['services_data']) ? $row['expected']['services_data'] : null;
		$pass = true;
		$this->message.="<br>customer aid <b>{$testFields['aid']}</b> identification<br>";
		if(isset($row['expected']['notExists'])){
				if(isset($returnRow['aid']) || isset($returnRow['sid'])){
					return false;
					$this->message.= " find no existng subscriber" .$this->fail;		
				} else {
					$this->message.= " Dont  find no existng subscriber" .$this->pass;	
					return true;
				}
		}
		foreach ($testFields as $field => $val){
			if($field){
				if(isset($returnRow[$field])){
				$this->message.="-- $field identification --<br>";
				if($val == $returnRow[$field]){
					$this->message.= " $field identification seccessfuly - $field $val" .$this->pass;
				} else {
					$pass = false;
					$this->message.= " $field isn't identification seccessfuly ,expected $field $val result {$returnRow[$field]}" .$this->fail;
				}
			} else {
				$pass = false;
				$this->message.= " $field isn't identification seccessfuly" .$this->fail;
			}
			}
			
		}
		if($services){
			$this->message.="services identification<br>";
			foreach ($services as $service){
				if(in_array($service, $returnRow['services'])){
					$this->message.= "customer service $service identification seccessfuly" .$this->pass;
				} else {
					$pass = false;
					$this->message.= "customer service $service isn't identification seccessfuly" .$this->fail;
				}
			}
			if($wrongServices = array_diff($returnRow['services'], $services)){
					$pass = false;
					$this->message.= "there are wrong services" ;
					foreach ($wrongServices as $wrong){
						$this->message.=$wrong." - ";
					}
					$this->message.=$this->fail;
			}
			
		}
		
		$sort = function ($a,$b){
			if ($a==$b) return 0;
			return ($a<$b)?-1:1;
		};
		@usort($services_data,$sort);
		usort($returnRow['services_data'],$sort);
		
		if($services_data){
			foreach ($services_data as $service_data){
				$checkService = current(array_filter($returnRow['services_data'], function(array $ser) use ($service_data) {
					if (isset($service_data['service_id'])){
						return $ser['service_id'] === $service_data['service_id'];
					}
					return $ser['name'] === $service_data['name'];
				}));
				if($checkService){
					 $checkService['to'] = $checkService['to']->toDateTime();
					 $checkService['from'] = $checkService['from']->toDateTime();
					foreach ($service_data as $key => $data){
					if($key != 'from' && $key != 'to'){
						if($service_data[$key]!==$checkService[$key]){
							$pass = false;
							$this->message.="services_data wrong for {$service_data['name']} ,expected $key => $data , result $key => $checkService[$key] ".$this->fail;
						}
					} else {
						$date = new DateTime($data);
					    $data= $date->getTimestamp();
						@$checkService['to'] = new DateTime( $checkService['to']->date);
					    $checkService['to'] = $date->getTimestamp();
						@$checkService['from'] = new DateTime( $checkService['from']->date);
					    $checkService['from'] = $date->getTimestamp();
						if($data !=  $checkService[$key]){
							$pass = false;
							$this->message.="services_data wrong for {$service_data['name']} ,expected $key => $data , result $key => $checkService[$key] ".$this->fail;
						}
					}
				}
				
				} else {
					$pass = false;
					$this->message.="services_data not fount for {$service_data['name']} ".$this->fail;
				}
				
			}
		}
		return $pass;
	}

	protected function fixRow($row, $key) {

		if (!array_key_exists('urt', $row)) {
			$row['urt'] = new MongoDate(time() + $key);
		} else {
			$row['urt'] = new MongoDate(strtotime($row['urt']));
		}
		if (!isset($row['type'])) {
			$row['type'] = 'mytype';
		}
		if (!isset($row['usaget'])) {
			$row['usaget'] = 'call';
		}
		if(isset($row['expected']['services_data'])){
			foreach ($row['expected']['services_data'] as $service){
				if(isset($row['expected']['services_data']['to'])){
			$row['expected']['services_data']['to'] = new MongoDate(strtotime($row['expected']['services_data']['to']));
		}
		if(isset($row['expected']['services_data']['from'])){
			$row['expected']['services_data']['from'] = new MongoDate(strtotime($row['expected']['services_data']['from']));
			}
			}
		}
		
		return $row;
	}

}
