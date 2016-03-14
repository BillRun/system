<?php

/**
 * Request Model
 * Model for Number Transaction operations.
 * 
 * @package         ApplicationModel
 * @subpackage      RequestModel
 * @copyright       Copyright (C) 2012-2015 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero Public License version 3 or later; see LICENSE.txt
 */

/**
 * Request Object
 * 
 * @package     ApplicationModel
 * @subpackage  RequestModel
 */
class Billrun_Auth implements Zend_Auth_Adapter_Interface {

	/**
	 * $_methodField - the table name to check
	 *
	 * @var string
	 */
	protected $_methodField = null;

	/**
	 * $_methodName - the table name to check
	 *
	 * @var string
	 */
	protected $_methodName = null;

	/**
	 * $_identityColumn - the column to use as the identity
	 *
	 * @var string
	 */
	protected $_identityField = null;

	/**
	 * $_credentialColumns - columns to be used as the credentials
	 *
	 * @var string
	 */
	protected $_credentialField = null;

	/**
	 * $_identity - Identity value
	 *
	 * @var string
	 */
	protected $_identity = null;

	/**
	 * $_credential - Credential values
	 *
	 * @var string
	 */
	protected $_credential = null;

	/**
	 * $_authenticateResultInfo
	 *
	 * @var array
	 */
	protected $_authenticateResultInfo = null;

	/**
	 * $_resultRow
	 *
	 * @var array
	 */
	protected $_resultRow = null;

	/**
	 * __construct() - Sets configuration options
	 *
	 * @param  Zend_Db_Adapter_Abstract $zendDb If null, default database adapter assumed
	 * @param  string                   $methodName
	 * @param  string                   $identityField
	 * @param  string                   $credentialField
	 * @return void
	 */
	public function __construct($methodField = null, $methodName = null, $identityField = null, $credentialField = null) {
		if (null !== $methodField) {
			$this->setMethodField($methodField);
		}
		if (null !== $methodName) {
			$this->setMethodName($methodName);
		}
		if (null !== $identityField) {
			$this->setIdentityField($identityField);
		}
		if (null !== $credentialField) {
			$this->setCredentialField($credentialField);
		}
	}

	/**
	 * setMethodName() - set the table name to be used in the select query
	 *
	 * @param  string $methodName
	 * @return Zend_Auth_Adapter_DbTable Provides a fluent interface
	 */
	public function setMethodName($methodName) {
		$this->_methodName = $methodName;
		return $this;
	}

	/**
	 * setMethodField() - set the table name to be used in the select query
	 *
	 * @param  string $methodField
	 * @return Zend_Auth_Adapter_DbTable Provides a fluent interface
	 */
	public function setMethodField($methodField) {
		$this->_methodField = $methodField;
		return $this;
	}

	/**
	 * setIdentityField() - set the column name to be used as the identity column
	 *
	 * @param  string $identityField
	 * @return Zend_Auth_Adapter_DbTable Provides a fluent interface
	 */
	public function setIdentityField($identityField) {
		$this->_identityField = $identityField;
		return $this;
	}

	/**
	 * setCredentialField() - set the column name to be used as the credential column
	 *
	 * @param  string $credentialField
	 * @return Zend_Auth_Adapter_DbTable Provides a fluent interface
	 */
	public function setCredentialField($credentialField) {
		$this->_credentialField = $credentialField;
		return $this;
	}

	/**
	 * setIdentity() - set the value to be used as the identity
	 *
	 * @param  string $value
	 * @return Zend_Auth_Adapter_DbTable Provides a fluent interface
	 */
	public function setIdentity($value) {
		$this->_identity = $value;
		return $this;
	}

	/**
	 * setCredential() - set the credential value to be used, optionally can specify a treatment
	 * to be used, should be supplied in parameterized form, such as 'MD5(?)' or 'PASSWORD(?)'
	 *
	 * @param  string $credential
	 * @return Zend_Auth_Adapter_DbTable Provides a fluent interface
	 */
	public function setCredential($credential) {
		$this->_credential = $credential;
		return $this;
	}

	public function authenticate() {
		$resultIdentity = $this->_authenticateHttpRequest();
		$this->_authenticateValidateResult($resultIdentity);
		return new Zend_Auth_Result(
			$this->_authenticateResultInfo['code'], $this->_authenticateResultInfo['identity'], $this->_authenticateResultInfo['messages']
		);
	}

	protected function _authenticateHttpRequest() {
		$internalModel = new Billrun_Util();//new Application_Model_Internal(array());
		$data = array(
			$this->_methodField => $this->_methodName,
			$this->_identityField => $this->_identity,
			$this->_credentialField => $this->_credential,
		);
		$json = $internalModel->sendRequest(Billrun_Factory::config()->getConfigValue('UrlToInternalResponse'), $data);
		$obj = @json_decode($json);
		return $obj;
	}

	protected function _authenticateValidateResult($resultIdentity) {
		if (empty($resultIdentity)) {
			$this->_authenticateResultInfo = array(
				'code' => Zend_Auth_Result::FAILURE,
				'identity' => $this->_identity,
				'messages' => array('Result empty'),
			);
		}
		$this->_resultRow = $resultIdentity;
		if (isset($resultIdentity->status) && $resultIdentity->status == '1') {
			$this->_authenticateResultInfo = array(
				'code' => Zend_Auth_Result::SUCCESS,
				'identity' => $this->_identity,
				'messages' => isset($resultIdentity->desc) ? array((string) $resultIdentity->desc) : array(),
			);
		} else {
			$this->_authenticateResultInfo = array(
				'code' => Zend_Auth_Result::FAILURE,
				'identity' => $this->_identity,
				'messages' => isset($resultIdentity->desc) ? array((string) $resultIdentity->desc) : array(),
			);
		}
	}

	/**
	 * _authenticateCreateAuthResult() - Creates a Zend_Auth_Result object from
	 * the information that has been collected during the authenticate() attempt.
	 *
	 * @return Zend_Auth_Result
	 */
	protected function _authenticateCreateAuthResult() {
		return new Zend_Auth_Result(
			$this->_authenticateResultInfo['code'], $this->_authenticateResultInfo['identity'], $this->_authenticateResultInfo['messages']
		);
	}

	public function getResultRowObject() {
		return $this->_resultRow;
	}

}
