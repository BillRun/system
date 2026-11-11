<?php

// The suite bootstrap re-points the Yaf local-namespace path to the Billapi module
// (yaf < 3.2), so Action_Base can no longer be autoloaded - load it explicitly.
require_once APPLICATION_PATH . '/application/helpers/Action/Base.php';
require_once APPLICATION_PATH . '/application/controllers/Api.php';
require_once APPLICATION_PATH . '/application/controllers/Billrun.php';

/**
 * The charge API (billrun/chargeAccount) validates aids/invoices/exclude_accounts
 * on an int-casted copy, then forks a CLI charge with the raw request values.
 * buildCommandByParams must hand every value to the shell as one literal
 * argument, so a value like "1,2;touch /tmp/pwned" can't run a command.
 * Each row runs the built string through a real shell and compares the argv
 * the forked process would receive.
 */
class ChargeCommandParamsTest extends \Codeception\Test\Unit {

	/**
	 * label => [request params, expected argv]
	 */
	protected function getCommandParamsTests() {
		return array(
			// regular values reach the CLI as before
			'aids list' => array(array('aids' => '1,2'), array('aids=1,2')),
			'quoted date keeps spaces, outer quotes stripped' => array(array('date' => '"2024-01-01 10:00:00"'), array('date=2024-01-01 10:00:00')),
			'several params keep their order' => array(array('aids' => '5', 'billrun_key' => '202409', 'mode' => 'charge'), array('aids=5', 'billrun_key=202409', 'mode=charge')),
			// shell metacharacters stay inside the argument
			'command separator' => array(array('aids' => '1,2;echo injected'), array('aids=1,2;echo injected')),
			'command substitution' => array(array('invoices' => '1$(echo injected)'), array('invoices=1$(echo injected)')),
			'backticks' => array(array('exclude_accounts' => '1`echo injected`'), array('exclude_accounts=1`echo injected`')),
			'single quote breakout' => array(array('aids' => "1' ; echo injected ; '"), array("aids=1' ; echo injected ; '")),
			'pipe and redirect' => array(array('date' => 'now | echo injected > /dev/null'), array('date=now | echo injected > /dev/null')),
		);
	}

	public function testChargeParamsAreSingleShellArguments() {
		$controller = (new ReflectionClass('BillrunController'))->newInstanceWithoutConstructor();
		$buildCommand = new ReflectionMethod('BillrunController', 'buildCommandByParams');
		$buildCommand->setAccessible(true);
		$printArgv = 'php -r ' . escapeshellarg('echo json_encode(array_slice($argv, 1));') . ' -- ';
		foreach ($this->getCommandParamsTests() as $label => $test) {
			list($params, $expectedArgv) = $test;
			$output = array();
			exec($printArgv . $buildCommand->invoke($controller, $params), $output);
			$this->assertSame(array(json_encode($expectedArgv)), $output, $label);
		}
	}

}
