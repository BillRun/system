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
     * Extend card expiration flag
     * @var boolean
     */
    protected $extend_card_expiration;

    /**
     * years to extend card expiration
     * @var int
     */
    protected $years_to_extend_card_exp;

    /**
     * Card types array
     * @var array 
     */
    protected $cardTypes = array("Regular" => "00", "Debit" => "01", "Rechargeable" => "06");

    /**
     * Transaction types array
     * @var array 
     */
    protected $tranTypes = array("RecurringDebit" => "11", "Debit" => "01", "credit" => "51");

    /**
     * Terminals
     * @var array
     */
    protected $terminals;

    /**
     * Mid
     * @var string
     */
    protected $mid;

    public function __construct($options = array()) {
        $this->card_expiration_field = Billrun_Util::getIn($options, "card_expiration_field_name", "card_expiration");
        $this->years_to_extend_card_exp = Billrun_Util::getIn($options, "years_to_extend_card_expiration", 3);
        $this->extend_card_expiration = Billrun_Util::getIn($options, "extend_card_expiration", true);
        $this->mid = Billrun_Util::getIn($options, "params.mid", null);
        $this->setTerminals(Billrun_Util::getIn($options, "params", []));
	}

    protected function setTerminals($options) {
        $this->terminals = [
            'charging_terminal' => Billrun_Util::getIn($options, "charging_terminal", null),
            'redirect_terminal' => Billrun_Util::getIn($options, "redirect_terminal", null),
            'onetime_terminal' => Billrun_Util::getIn($options, "onetime_terminal", null)
        ];
    }

    /**
     * Function to update cp request file line fields
     * @var array $type - cpf type
     * @var array $payment - request pending payment
     * @var array $params - list of params that the data line will pull from
     * @var array $extra_saved_fields - array of custom field_name => field_value that are added to cpf bills
     * @var array $cpf_custom_field_names - array of custom field names that are added to cpf bills
     * @var array $account - account data
     * @var Billrun_Generator_PaymentGateway_Custom - cpf generator
     */
    public function beforeGettingRequestFilePaymentDataLine($type,$payment, &$params, &$extra_saved_fields ,&$cpf_custom_field_names, $account, $cpf_generator) {
        if ($type != 'transactions_request') {
			return;
		}
        $config = $cpf_generator->getPgConfig();
        $terminal_and_tran_type = $this->getTerminalAndTransactionType($account, $payment);
        foreach ($config['generator']['data_structure'] as $index => &$param_obj) {
            switch ($param_obj['name']) {
                case 'terminal_type':
                case 'terminal_number':
                    $terminal_type = $terminal_and_tran_type['terminal'];
                    $terminal_number = $this->getTerminalNumber($account, $terminal_type);
                    $param_obj['hard_coded_value'] = $param_obj['name'] == 'terminal_type' ? $terminal_type : $terminal_number;
                    break;
                case 'card_expiration':
                    $param_obj['hard_coded_value'] = $this->getCardExpiration($account);
                    break;
                case 'transaction_type':
                    $tran_type = $terminal_and_tran_type['transaction'];
                    $param_obj['hard_coded_value'] = $tran_type;
                    break;
                case 'auth_number':
                    if ($terminal_and_tran_type['transaction'] === $this->tranTypes['RecurringDebit']) {
                        Billrun_Factory::log()->log("creditGuardPlugin : transaction type is RecurringDebit for account " . $account['aid'] . ". Getting it's auth number", Zend_Log::DEBUG);
                        $auth = $this->getAuthNumber($account);
                        if (empty($auth)) {
                            unset($param_obj['hard_coded_value']);
                        } else {
                            $param_obj['hard_coded_value'] = $auth;
                        }
                    } else {
                        unset($config[$index]);
                    }
                    break;
                default:
                    continue;
                    break;
            }
        }
        $cpf_generator->setPgConfig($config);
    }

    protected function getCardExpiration($account) {
        $gatewayDetails = $account['payment_gateway'];
        $current_gateway_details = Billrun_Util::getIn($gatewayDetails, "active", null);
        if (empty($current_gateway_details)) {
            Billrun_Factory::log()->log("creditGuardPlugin : No active payment_gateway details of account " . $account['aid'] . ". Missing card expiration field data in request file line", Zend_Log::ALERT);
            return false;
        } else {
            Billrun_Factory::log()->log("creditGuardPlugin : Found active payment_gateway details of account " . $account['aid'], Zend_Log::DEBUG);
        }
        Billrun_Factory::log()->log("creditGuardPlugin : calculating card expiration extension for account " . $account['aid'], Zend_Log::DEBUG);
        if (!$this->extend_card_expiration) {
            Billrun_Factory::log()->log("creditGuardPlugin : Extend card expiration flag is off. Saving the original card expiration field value", Zend_Log::DEBUG);
            return $current_gateway_details[$this->card_expiration_field];
        } else {
            Billrun_Factory::log()->log("creditGuardPlugin : Extend card expiration flag is on", Zend_Log::DEBUG);
            $current_card_expiration = $current_gateway_details[$this->card_expiration_field];
            Billrun_Factory::log()->log("creditGuardPlugin : Current card expiration of account " . $account['aid'] . " is " . $current_card_expiration, Zend_Log::DEBUG);
            $file_card_expiration = substr($current_card_expiration, 0, 2) . ((substr($current_card_expiration, 2, 4) + $this->years_to_extend_card_exp) % 100);
            Billrun_Factory::log()->log("creditGuardPlugin : Card expiration value that will be insert to the request file for account " . $account['aid'] . " is " . $file_card_expiration, Zend_Log::DEBUG);
            return $file_card_expiration;
        }
    }

    protected function getTerminalAndTransactionType($account, $payment) {
        $gatewayDetails = $account['payment_gateway']['active'];
        Billrun_Factory::log()->log("creditGuardPlugin : calculating CG terminal and transaction type for account " . $account['aid'], Zend_Log::DEBUG);
        if (isset($gatewayDetails['card_type']) && (in_array($gatewayDetails['card_type'], [$this->cardTypes['Debit'], $this->cardTypes['Rechargeable']]))) {
            $res = ['terminal' => 'onetime_terminal', 'transaction' => $this->tranTypes['Debit']];
        } else {
            $res = ['terminal' => 'charging_terminal', 'transaction' => $this->tranTypes['RecurringDebit']];
        }
        if ($payment->getDir() == 'tc') {
            $res['transaction'] = $this->tranTypes['credit'];
        }
        return $res;
    }

    protected function getTerminalNumber($account, $terminal_type) {
        Billrun_Factory::log()->log("creditGuardPlugin : according to the terminal conditions, account " . $account['aid'] . " terminal type is " . $terminal_type, Zend_Log::DEBUG);
        if (!empty($terminal_number = Billrun_Util::getIn($this->terminals, $terminal_type, null))) {
            Billrun_Factory::log()->log("creditGuardPlugin : according to the terminal conditions and pg config, account " . $account['aid'] . " terminal number is " . $terminal_number, Zend_Log::DEBUG);
        } else {
            Billrun_Factory::log()->log("creditGuardPlugin : couldn't find terminal " . $terminal_type . " number in config for account " . $account['aid'] . ". Charging terminal value was taken", Zend_Log::ALERT);
            $terminal_number = $this->terminals['charging_terminal'];            
        }
        return $terminal_number;
    }

    protected function getAuthNumber($account) {
        $gatewayDetails = $account['payment_gateway']['active'];
        Billrun_Factory::log()->log("creditGuardPlugin : calculating auth number for account " . $account['aid'], Zend_Log::DEBUG);
        $auth_num = Billrun_Util::getIn($gatewayDetails, "auth_number", null);
        if (empty($auth_num)) {
            Billrun_Factory::log()->log("creditGuardPlugin : auth number is empty for account " . $account['aid'], Zend_Log::ALERT);
            return null;
        }
        Billrun_Factory::log()->log("creditGuardPlugin : found auth number for account " . $account['aid'], Zend_Log::DEBUG);
        return $auth_num;
    }

    public function getConfigurationDefinitions() {
		return [
            [
				'type' => 'boolean',
				'field_name' => 'extend_card_expiration',
				'title' => 'Extend card expiration',
				'mandatory' => false,
				'editable' => true,
				'display' => true,
				'nullable' => false,
				'default' => true
			],
            [
				"type" => "string",
				"field_name" => "card_expiration_field_name",
				"title" => "Card expiration field name",
				"editable" => true,
				"display" => true,
				"nullable" => false,
				"mandatory" => true,
                "default" => "card_expiration"
			],
            [
				"type" => "number",
				"field_name" => "years_to_extend_card_expiration",
				"title" => "Years to extend card expiration",
				"editable" => true,
				"display" => true,
				"nullable" => false,
				"mandatory" => true,
                "default" => 3
			]
		];
	}
}
