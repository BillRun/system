<?php
/**
 * PHP Anonymous Object
 */
class Billrun_AnObj
{
	public function __construct(array $options)
	{
		$this->data = $options;
	}
	public function get($prop) {
		return $this->data[$prop];
	}
}