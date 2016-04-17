<?php

/**
 * PHP Anonymous Object
 */
class Billrun_AnObj {

	public function __construct(array $options) {
		$this->data = $options;
	}

	/**
	 * Get a property stored in the object data.
	 * @param type $prop - Name of the property
	 * @param type $default - Default value if property not found, default is null.
	 * @return type
	 */
	public function get($prop, $default = null) {
		if (!isset($this->data[$prop])) {
			return $default;
		}
		return $this->data[$prop];
	}

}
