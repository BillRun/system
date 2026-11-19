<?php

/**
 * Unit test for Billrun_Sms_Smpp::send() - the SMS must reach the SMSC (BRCD-5650).
 * Since PHP 8.0 socket_create() returns a Socket object and not a resource. The transport of the SMPP library
 * (alexandr-mironov/php-smpp) checks is_resource() in isOpen(), so on PHP 8 it was "closed" also when it was connected:
 * send() opened the connection, sent nothing on it and did not fail. Billrun_Sms_Smpp_Socket fixes isOpen().
 * The SMSC of the test is a mockup (smscMockup.php) that runs as a process on a free local port
 * and reports the PDUs it gets, so nothing leaves the machine.
 */
class SmppTest extends \Codeception\Test\Unit {

	const MESSAGE_ID = 'MSG-1'; // the message id that the SMSC mockup gives

	/**
	 * @var \UnitTester
	 */
	protected $tester;
	protected $smsc;
	protected $smscOutput;
	protected $smscPort;

	protected function _before() {
		$this->smsc = proc_open([PHP_BINARY, __DIR__ . '/smscMockup.php'], [1 => ['pipe', 'w']], $pipes);
		$this->smscOutput = $pipes[1];
		$this->smscPort = (int) fgets($this->smscOutput);
		$this->assertGreaterThan(0, $this->smscPort, 'The SMSC mockup did not start');
	}

	protected function _after() {
		fclose($this->smscOutput);
		proc_terminate($this->smsc);
		proc_close($this->smsc);
	}

	public function testSendReachesTheSmsc() {
		$smser = Billrun_Sms_Abstract::getInstance([
			'type' => 'smpp',
			'host' => '127.0.0.1',
			'port' => (string) $this->smscPort,
			'user' => 'billrun',
			'token' => 'secret',
			'from' => 'BillRun',
		]);
		$smser->setTo('972501234567');
		$smser->setBody('BRCD-5650 unit test');

		$messageId = $smser->send();
		unset($smser); // the session is closed (unbind) when the sender is destroyed

		$this->assertSame([
			['command' => 'bind_transmitter', 'system_id' => 'billrun', 'password' => 'secret'],
			['command' => 'submit_sm', 'from' => 'BillRun', 'to' => '972501234567', 'text' => 'BRCD-5650 unit test'],
			['command' => 'unbind'],
		], $this->pdusOfSmsc(), 'The PDUs that the SMSC got');
		// the library returns the message id with the null terminator of the PDU
		$this->assertSame(self::MESSAGE_ID, rtrim((string) $messageId, "\0"), 'send() returns the message id of the SMSC');
	}

	/**
	 * The PDUs that the SMSC mockup got until the client disconnected.
	 */
	protected function pdusOfSmsc() {
		$pdus = [];
		foreach (array_filter(explode("\n", stream_get_contents($this->smscOutput))) as $line) {
			$pdus[] = json_decode($line, true);
		}
		return $pdus;
	}

}
