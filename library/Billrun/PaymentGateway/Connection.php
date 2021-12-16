<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2019 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing payment gateways connection class
 *
 * @since    5.10
 */
abstract class Billrun_PaymentGateway_Connection {
	
	use Billrun_Traits_FileActions;

	protected $host;
	protected $username;
	protected $password;
	protected $connection = false;
	protected $remoteDir;
	protected $connectionDetails;
	protected $filenameRegex;
	protected $recursive_mode;
	protected $workspace;
	protected $limit;
	protected $fileType;
	protected $localDir;

	public function __construct($options) {
		if (!isset($options['connection_type']) || !isset($options['host'])  || !isset($options['user']) ||
			!isset($options['password'])) {
			throw new Exception('Missing connection details');
		}
		$this->host = $options['host'];
		$this->username = $options['user'];
		$this->password = $options['password'];
		$this->remoteDir = isset($options['remote_directory']) ? $options['remote_directory'] : '';
		$this->localDir = isset($options['export_directory']) ? $options['export_directory'] : '';
		$this->recursive_mode = isset($options['recursive_mode']) ? $options['recursive_mode'] : false;
		$this->filenameRegex = !empty($options['filename_regex']) ? $options['filename_regex'] : '/.*/';
		$this->workspace = Billrun_Util::getBillRunSharedFolderPath(Billrun_Util::getFieldVal($options['workspace'], 'workspace'));
		if (isset($options['backup_path'])) {
			$this->backupPaths = Billrun_Util::getBillRunSharedFolderPath($options['backup_path']);
		} else {
			$this->backupPaths = Billrun_Util::getBillRunSharedFolderPath(Billrun_Factory::config()->getConfigValue($this->getType() . '.backup_path', './backups/' . $this->getType()));
		}
		if (isset($options['limit']) && $options['limit']) {
			$this->limit = $options['limit'];
		}
		$this->fileType = isset($options['file_type']) ? $options['file_type'] : null;
	}

	/**
	 * 
	 * @param string $name the payment gateway name
	 * @return Billrun_PaymentGateway
	 */
	public static function getInstance($connectionDetails) {
		$subClassName = __CLASS__ . '_' . ucfirst($connectionDetails['connection_type']);
		if (@class_exists($subClassName)) {
			$connection = new $subClassName($connectionDetails);
		}
		return isset($connection) ? $connection : NULL;
	}
	
	
	/**
	 * Get the type name of the current object.
	 * @return string conatining the current.
	 */
	public function getType() {
		return static::$type;
	}

	/**
	 * method to get receiver settings in config.
	 * 
	 * @param mixed $options
	 * @return mixed recevier settings
	 * 
	 */
	public static function getReceiverSettings($options) {
		$type = $options['type'];
		if (!isset($options['payment_gateway'])) {
			throw new Exception('Missing payment gateway');
		}
		$gateway = $options['payment_gateway'];
		$pgReceivers = array();
		$paymentGatewaySettings = array_filter(Billrun_Factory::config()->getConfigValue('payment_gateways'), function($paymentGateway) use ($gateway) {
			return ($paymentGateway['name'] === $gateway) && !empty($paymentGateway['custom']);
		});
		if ($paymentGatewaySettings) {
			$paymentGatewaySettings = current($paymentGatewaySettings);
		}
		
		$transactionsResponses = !empty($paymentGatewaySettings[$type]) ? $paymentGatewaySettings[$type] : array();
		foreach ($transactionsResponses as $key => $gatewaySettings) {
			if (!empty($gatewaySettings['receiver'])) {
				$pgReceivers[$gatewaySettings['file_type']] = $gatewaySettings['receiver'];
			}
		}
		
		return $pgReceivers;
	}

	abstract public function export($fileName);
	abstract public function receive();
	
	public function getWorkspace() {
		return $this->workspace;
	}
}
