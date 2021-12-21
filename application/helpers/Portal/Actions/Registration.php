<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2020 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Customer Portal aregistation actions
 * 
 * @package  Billing
 * @since    5.14
 */
class Portal_Actions_Registration extends Portal_Actions {

	const VALIDITY_TIME = [
		'DEFAULT' => '24 hours'
	];

	const TOKEN_TYPE_EMAIL_VERIFICATION = 'email_verification';
        const TOKEN_TYPE_RESET_PASSWORD = 'reset_password';
        const TOKEN_TYPE_WELCOME_ACCOUNT = 'welcome_account';
        
    /**
     * send authentication email with 1-time token
	 *
     * @param  array $params
     * @return void
     */
    public function sendAuthenticationEmail($params = []) {
		$username = $params['username'] ?? '';
                if (empty($username)) {
			throw new Portal_Exception('missing_parameter', '', 'Missing parameter: "username"');
		}
                $email = $this->getFieldByAuthenticationField('email', $username) ?? '';
		if (empty($email)) {
			throw new Portal_Exception('missing_parameter', '', 'Missing parameter: "email"');
		}				
                $params['email'] = $email;
                $token = $this->generateToken($params, self::TOKEN_TYPE_EMAIL_VERIFICATION);	
                $subject = $this->getEmailSubject('email_authentication');
                $replaces = array_merge([
			'[[name]]' => ucfirst($this->getFieldByAuthenticationField('lastname', $username)). " " . ucfirst($this->getFieldByAuthenticationField('firstname', $username)),
			'[[email_authentication_link]]' => rtrim(Billrun_Util::getCompanyWebsite(), '/') . '/signup?token=' . $token . '&username=' . $username,
		], $this->BuildReplacesforCompanyInfo());
		$body = $this->getEmailBody('email_authentication', $replaces);
		if (!Billrun_Util::sendMail($subject, $body, [$email], [], true)) {
			$this->log("Portal_Actions_Registration::sendAuthenticationEmail - failed to send Email to {$email}", Billrun_Log::ERR);
			throw new Portal_Exception('send_email_failed');
		}
	}
        
    /**
     * send email to reset password with 1-time token
     *
     * @param  array $params
     * @return void
     */
    public function sendResetPasswordEmail($params = []) {
		$username = $params['username'] ?? '';
                if (empty($username)) {
			throw new Portal_Exception('missing_parameter', '', 'Missing parameter: "username"');
		}
                $email =  $this->getFieldByAuthenticationField('email', $username) ?? '';
		if (empty($email)) {
			throw new Portal_Exception('missing_parameter', '', 'Missing parameter: "email"');
		}		
                $params['email'] = $email;
                $token = $this->generateToken($params, self::TOKEN_TYPE_RESET_PASSWORD);
                $subject = $this->getEmailSubject('reset_password');
                $replaces = array_merge([
			'[[name]]' => ucfirst($this->getFieldByAuthenticationField('lastname', $username)). " " . ucfirst($this->getFieldByAuthenticationField('firstname', $username)),
			'[[reset_password_link]]' => rtrim(Billrun_Util::getCompanyWebsite(), '/') . '/reset-password?token=' . $token . '&username=' . $username,
                        '[[link_expire]]' => $this->getValidity('reset_password'),
                        
		], $this->BuildReplacesforCompanyInfo());
		$body = $this->getEmailBody('reset_password', $replaces);
                
		if (!Billrun_Util::sendMail($subject, $body, [$email], [], true)) {
			$this->log("Portal_Actions_Registration::sendResetPasswordEmail - failed to send Email to {$email}", Billrun_Log::ERR);
			throw new Portal_Exception('send_email_failed');
		}
	}
        
        
    /**
     * send welcome email to account
     *
     * @param  array $params
     * @return void
     */
    public function sendWelcomeEmail($params = []) {
                $username = $params['username'] ?? '';
                if (empty($username)) {
			throw new Portal_Exception('missing_parameter', '', 'Missing parameter: "username"');
		}                
                $email = $this->getFieldByAuthenticationField('email', $username) ?? '';
		if (empty($email)) {
			throw new Portal_Exception('missing_parameter', '', 'Missing parameter: "email"');
		}		
                $params['email'] = $email;
                $token = $this->generateToken($params, self::TOKEN_TYPE_WELCOME_ACCOUNT); 
                $subject = $this->getEmailSubject('welcome_account');
                $replaces = array_merge([
                        '[[name]]' => ucfirst($this->getFieldByAuthenticationField('lastname', $username)). " " . ucfirst($this->getFieldByAuthenticationField('firstname', $username)),
                        '[[username]]' => $username,
                        '[[access_from]]' => $params['access_from'] ?? 'now', //todo ::check from where need to take this param?? from api params? config? 
                        '[[link]]' =>  rtrim(Billrun_Util::getCompanyWebsite(), '/') . '/signup?token=' . $token . '&username=' . $username,
                ], $this->BuildReplacesforCompanyInfo());
		$body = $this->getEmailBody('welcome_account', $replaces);
                
		if (!Billrun_Util::sendMail($subject, $body, [$email], [], true)) {
			$this->log("Portal_Actions_Registration::sendWelcomeEmail - failed to send Email to {$email}", Billrun_Log::ERR);
			throw new Portal_Exception('send_email_failed');
		}
	}
          
        /**
         * set user password in the system after forgot password
         * @param array $params
         */
        public function forgotPassword($params = []) {
            $this->signUp($params,  self::TOKEN_TYPE_RESET_PASSWORD);
        }
        
        /**
	 * sign up the user in the system to allow him authenticate using OAuth2
	 *
	 * @param  array $params
         * @parm string $tokenType 
	 */
	public function signUp($params = [], $tokenType = self::TOKEN_TYPE_WELCOME_ACCOUNT) {
		$token = $params['token'] ?? '';
		if (empty($token)) {
			throw new Portal_Exception('missing_parameter', '', 'Missing parameter: "token"');
		}
                $username = $params['username'] ?? '';
                if (empty($username)) {
			throw new Portal_Exception('missing_parameter', '', 'Missing parameter: "username"');
		}
                $password = $params['password'] ?? '';
		if (empty($password)) {
			throw new Portal_Exception('missing_parameter', '', 'Missing parameter: "password"');
		}
                $email = $this->getFieldByAuthenticationField('email', $username) ?? '';
		if (empty($email)) {
			throw new Portal_Exception('missing_parameter', '', 'Missing parameter: "email"');
		}
                $params['email'] = $email;
                $params[$this->params['authentication_field']] = $username;
		if (!$this->validateToken($token, $params, $tokenType)) {
			throw new Portal_Exception('authentication_failed');
		}

		Billrun_Factory::oauth2()->getStorage('user_credentials')->setUser($username, $password);
	}
        
	/**
	 * sign the user in the system to allow him authenticate using OAuth2
	 *
	 * @param  array $params
	 * @return void
	 */
	public function signIn($params = []) {
		$token = $params['token'] ?? '';
		if (empty($token)) {
			throw new Portal_Exception('missing_parameter', '', 'Missing parameter: "token"');
		}

		$email = $params['email'] ?? '';
		if (empty($email)) {
			throw new Portal_Exception('missing_parameter');
		}

		$password = $params['password'] ?? '';
		if (empty($password)) {
			throw new Portal_Exception('missing_parameter', '', 'Missing parameter: "password"');
		}
                
		if (!$this->validateToken($token, $params, self::TOKEN_TYPE_EMAIL_VERIFICATION)) {
			throw new Portal_Exception('authentication_failed');
		}

		Billrun_Factory::oauth2()->getStorage('user_credentials')->setUser($params['id'] ?? $email, $password);
	}
        
        /**
	 * get the subject email
	 *
         * @param  string $path - the path of the requested email body
	 * @return string
	 */
	protected function getEmailSubject($path) {
		return Billrun_Factory::config()->getConfigValue('email_templates.' . $path . '.subject' , '');
	}
        
        /**
	 * get the body of the Email
	 *
         * @param  string $path - the path of the requested email body
	 * @return string
	 */
	protected function getEmailBody($path, $replaces) {
		$body = Billrun_Factory::config()->getConfigValue('email_templates.' . $path . '.content', '');
		
		return str_replace(array_keys($replaces), array_values($replaces), $body);
	}
	
	/**
	 * generate token by given data
	 *
	 * @param  array $data
	 * @param  string $type
	 * @param  int $try
	 * @return string
	 */
	protected function generateToken($data, $type = '', $try = 0) {
		$secret = $this->params['token_secret'] ?? '';
		if (empty($secret)) {
			throw new Portal_Exception('token_secret_missing');
		}

		$tokenFields = [
			$this->params['authentication_field'],
			'email',
		];
        $params = [
            'validity' => $this->getValidity($type),
            'try' => $try,
        ];
        return $this->generateHash($data, $tokenFields, $secret, $params);
	}
	
	/**
	 * validate given token by token type
	 *
	 * @param  string $token
	 * @param  array $data
	 * @param  string $type
	 * @return void
	 */
	protected function validateToken($token, $data, $type = '') {
        if (empty($token)) {
            return false;
        }

        $validity = $this->getValidity($type);
        $tries = intval(explode(' ', $validity)[0]);
        for ($i = 0; $i <= $tries; $i++) {
            if ($token == $this->generateToken($data, $type, $i)) {
                return true;
            }
        }

        return false;
    }
    
    /**
     * generate hash based on given fields, secret and validity received
     *
     * @param  array $data
     * @param  array $tokenFields
     * @param  string $secret
     * @param  array $params
     * @return string
     */
    protected function generateHash($data, $tokenFields, $secret, $params = []) {
        $try = $params['try'] ?? 0;
        $validity = $params['validity'] ?? 0;
		$arr = [];
		
		foreach ($tokenFields as $tokenField) {
            if (!empty($data[$tokenField])) {
				$arr[$tokenField] = $data[$tokenField];
            }
        }

        if (empty($arr) || empty($secret)) {
            throw new Portal_Exception('missing_parameter');
        }

        $hashInterval = explode(' ', $validity)[1];
        switch ($hashInterval) {
            case 'hours':
            case 'hour':
                $dateFormat = 'Ymdh';
                break;
            case 'minutes':
            case 'minute':
                $dateFormat = 'Ymdhi';
                break;
            case 'days':
            case 'day':
                $dateFormat = 'Ymd';
                break;
            case 'months':
            case 'month':
                $dateFormat = 'Ym';
                break;
            case 'years':
            case 'year':
                $dateFormat = 'Y';
                break;
            default:
				throw new Portal_Exception('missing_parameter');
        }

        $arr['scrt'] = $secret . date($dateFormat, strtotime("-{$try} {$hashInterval}"));
        $token = md5(serialize($arr)); 
        return $token;
    }
    
    /**
     * get token validity by token type
     *
     * @param  string $type
     * @return string
     */
    protected function getValidity($type = '') {
        return self::VALIDITY_TIME[$type] ?? self::VALIDITY_TIME['DEFAULT'];
    }

    /**
	 * Authorize the request.
	 * all registration actions does not require authorization
	 *
	 * @param  string $action
	 * @param  array $params
	 * @return boolean
	 */
    protected function authorize($action, &$params = []) {
		return true;
	}
        
        
    protected function BuildReplacesforCompanyInfo(){
        return [
            '[[company_email]]' => Billrun_Util::getCompanyEmail(),
            '[[company_name]]' => Billrun_Util::getCompanyName(),
            '[[company_address]]' => Billrun_Util::getCompanyAddress(),
            '[[company_phone]]' => Billrun_Util::getCompanyPhone(),
            '[[company_website]]' => Billrun_Util::getCompanyWebsite()  
            //maybe need to add Activity time for salt template?? 
        ];
    } 

    protected function getFieldByAuthenticationField($field, $username) {
        $query = [
          $this->params['authentication_field'] => $username
        ];
        $billapiParams = $this->getBillApiParams('accounts', 'uniqueget', $query);
	$res = current($this->runBillApi($billapiParams));
        if(empty($res)){
            throw new Portal_Exception('no_account', '', 'No account found');
        }
        return $res[$field];
    }
}
