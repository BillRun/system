<?php

/**
 * Calculator cpu plugin make the calculative operations in the cpu (before line inserted to the DB)
 *
 * @package  Application
 * @subpackage Plugins
 * @since    5.16
 */
class creditGuardPlugin extends Billrun_Plugin_BillrunPluginBase {

	/**
	 * plugin name
	 *
	 * @var string
	 */
    protected $name = 'creditGuard';

    /**
     * card expiration field name
     * @var string
     */
	protected $card_expiration_field;

    /**
     * credit guard config
     * @var array
     */
    protected $cgConfig;
    
    /**
     * Extend card expiration flag
     * @var boolean
     */
    protected $extend_card_expiration;

    /**
     * Card types array
     * @var array 
     */

     
    protected $cardTypes = array("Regular" => "00", "Debit" => "01", "Rechargeable" => "06");

    public function __construct($options = array()) {
        $this->card_expiration_field = Billrun_Util::getIn($options, "card_expiration_field_name", "card_expiration");
        $this->cgConfig = Billrun_Factory::config()->getConfigValue('creditguard');
        $this->extend_card_expiration = Billrun_Util::getIn($options, "extend_card_expiration", true);
	}

    /**
     * Function to update cp request file line fields
     * @var Billrun_Generator_PaymentGateway_Custom - cpf generator
     * @var array $account - account data
     * @var array $params - list of params that the data line will pull from
     * @var array $bill - relevnat bill to pay
     * @var array $payment - request pending payment
     */
    public function beforeGetTransactionsRequestDataLine ($cpf_generatore, $account, &$params, $bill, $payment) {
        $gatewayDetails = $account['payment_gateway'];
        Billrun_Factory::log()->log("creditGuardPlugin : calculating card expiration extension for account " . $account['aid'], Zend_Log::DEBUG);
        if (!$this->extend_card_expiration) {
            Billrun_Factory::log()->log("creditGuardPlugin : Extend card expiration flag is off", Zend_Log::DEBUG);
        } else {
            Billrun_Factory::log()->log("creditGuardPlugin : Extend card expiration flag is on", Zend_Log::DEBUG);
            $years = $this->cgConfig['years_to_extend_card_expiration'];
            $current_gateway_details = Billrun_Util::getIn($gatewayDetails, "active", null);
            if (empty($current_gateway_details)) {
                Billrun_Factory::log()->log("creditGuardPlugin : No active payment_gateway details of account " . $account['aid'] . ". Missing card expiration field data in request file line", Zend_Log::ALERT);
                return;
            }
            Billrun_Factory::log()->log("creditGuardPlugin : Found active payment_gateway details of account " . $account['aid'], Zend_Log::DEBUG);
            $current_card_expiration = $current_gateway_details[$this->card_expiration_field];
            Billrun_Factory::log()->log("creditGuardPlugin : Current card expiration of account " . $account['aid'] . " is " . $current_card_expiration, Zend_Log::DEBUG);
            $file_card_expiration = substr($current_card_expiration, 0, 2) . ((substr($current_card_expiration, 2, 4) + $years) % 100);
            Billrun_Factory::log()->log("creditGuardPlugin : Card expiration value that will be insert to the request file for account " . $account['aid'] . " is " . $file_card_expiration, Zend_Log::DEBUG);
            $params['card_expiration'] = $file_card_expiration;
        }

        Billrun_Factory::log()->log("creditGuardPlugin : calculating CG terminal for account " . $account['aid'], Zend_Log::DEBUG);
        if (isset($gatewayDetails['card_type']) && (in_array($gatewayDetails['card_type'], [$this->cardTypes['Debit'], $this->cardTypes['Rechargeable']]))) {
			$params['terminal_type'] = 'onetime_terminal';
		} else {
            $params['terminal_type'] = 'charging_terminal';
        }
        Billrun_Factory::log()->log("creditGuardPlugin : according to the terminal conditions, account " . $account['aid'] . " terminal type is " . $params['terminal_type'], Zend_Log::DEBUG);
    }




}
