<?php

class Zend_Auth_Storage_Yaf extends Zend_Auth_Storage_Session {

	public function __construct($namespace = self::NAMESPACE_DEFAULT, $member = self::MEMBER_DEFAULT) {
		$this->_namespace = $namespace;
		$this->_member = $this->_namespace . $member;
		$this->_session = @Yaf_session::getInstance();
	}

}
