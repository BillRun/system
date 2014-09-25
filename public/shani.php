<?php
sleep(11);
die('dfkvb');
class A {

	public function __construct() {
		die();
		gc_enable();
		$m = new MongoClient("mongodb://reading:guprgri@rp03");
		mongodb://[username:password@]host1[:port1][,host2[:port2:],...]/db
		$db = $m->selectDB('billing');
		$collection = new MongoCollection($db, 'lines');

		$query = array('aid' => 2065512);
		$this->printMemoryUsage("before query");
		$cursor = $collection->find($query)->limit(200000);
		$this->printMemoryUsage("after query");
		$result = array();
		$counter = 0;

		foreach ($cursor as $entity) {
			$result[$counter] = $entity;
			$counter++;
//			$result[] = $entity;
		}
//		for ($i = 0; $i < 200000; $i++) {
//			for ($j = 0; $j < 42; $j++) {
////			for ($j = 0; $j < 30; $j++) {
//				$result[$i][$j] = md5($i . "_" . $j);
//			}
//		}
		$this->printMemoryUsage("after load array");

		unset($result);
		$this->printMemoryUsage("after unset array");
		gc_collect_cycles();
		$this->printMemoryUsage("after gb");
		$cursor->reset();
		$this->printMemoryUsage("after cursor reset");
		gc_collect_cycles();
		$this->printMemoryUsage("after gb");
		unset($cursor);
		$this->printMemoryUsage("after unset cursor");
		gc_collect_cycles();
		$this->printMemoryUsage("after gb");
	}

	public function printMemoryUsage($message = "") {
		$memory = memory_get_usage();
		echo ($message == "" ? "" : $message . '. ') . 'Usage: ' . round($memory / 1048576, 2) . ' MB</br>';
		return $memory;
	}

	public function printSomething($text) {
		echo $text . "</br>";
	}

	public function sizeofvar($var) {
		$start_memory = memory_get_usage();
		$tmp = unserialize(serialize($var));
		return memory_get_usage() - $start_memory;
	}

}

class B {

	public $lines = array();

}

$a = new A();
?>
