<?php

/**
 * BRCD-785 customer hierarchy - DB-free unit coverage for the pure helper logic
 * (payer detection and parent_aid normalization used by the pay command).
 */
class HierarchyTest extends \Codeception\Test\Unit
{
	/**
	 * @var \UnitTester
	 */
	protected $tester;

	protected function invokeStatic($class, $method, array $args)
	{
		$ref = new ReflectionMethod($class, $method);
		$ref->setAccessible(true);
		return $ref->invoke(null, ...$args);
	}

	public function testAccountIsPayerDefaultsToTrue()
	{
		$this->assertTrue($this->invokeStatic('Billrun_Bill_Payment', 'accountIsPayer', [[]]), 'missing is_payer defaults to payer');
		$this->assertTrue($this->invokeStatic('Billrun_Bill_Payment', 'accountIsPayer', [['is_payer' => null]]), 'null is_payer defaults to payer');
	}

	public function testAccountIsPayerBooleanAndIntAndString()
	{
		$this->assertTrue($this->invokeStatic('Billrun_Bill_Payment', 'accountIsPayer', [['is_payer' => true]]));
		$this->assertFalse($this->invokeStatic('Billrun_Bill_Payment', 'accountIsPayer', [['is_payer' => false]]));
		$this->assertTrue($this->invokeStatic('Billrun_Bill_Payment', 'accountIsPayer', [['is_payer' => 1]]));
		$this->assertFalse($this->invokeStatic('Billrun_Bill_Payment', 'accountIsPayer', [['is_payer' => 0]]));
		$this->assertFalse($this->invokeStatic('Billrun_Bill_Payment', 'accountIsPayer', [['is_payer' => 'false']]));
		$this->assertFalse($this->invokeStatic('Billrun_Bill_Payment', 'accountIsPayer', [['is_payer' => '0']]));
		$this->assertTrue($this->invokeStatic('Billrun_Bill_Payment', 'accountIsPayer', [['is_payer' => 'true']]));
	}

	public function testParentAidOf()
	{
		$this->assertNull($this->invokeStatic('Billrun_Bill_Payment', 'parentAidOf', [[]]));
		$this->assertNull($this->invokeStatic('Billrun_Bill_Payment', 'parentAidOf', [['parent_aid' => null]]));
		$this->assertNull($this->invokeStatic('Billrun_Bill_Payment', 'parentAidOf', [['parent_aid' => '']]));
		$this->assertSame(123, $this->invokeStatic('Billrun_Bill_Payment', 'parentAidOf', [['parent_aid' => 123]]));
		$this->assertSame(123, $this->invokeStatic('Billrun_Bill_Payment', 'parentAidOf', [['parent_aid' => '123']]));
	}

	public function testUtilToBool()
	{
		$this->assertTrue(Billrun_Util::toBool(true, false));
		$this->assertFalse(Billrun_Util::toBool(false, true));
		$this->assertTrue(Billrun_Util::toBool(1, false));
		$this->assertFalse(Billrun_Util::toBool(0, true));
		$this->assertTrue(Billrun_Util::toBool('true', false));
		$this->assertFalse(Billrun_Util::toBool('false', true));
		$this->assertFalse(Billrun_Util::toBool('0', true));
		$this->assertFalse(Billrun_Util::toBool('off', true));
		// null and empty string fall back to the provided default
		$this->assertTrue(Billrun_Util::toBool(null, true));
		$this->assertFalse(Billrun_Util::toBool(null, false));
		$this->assertTrue(Billrun_Util::toBool('', true));
	}
}
