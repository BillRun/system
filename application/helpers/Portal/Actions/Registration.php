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
		'default' => '24 hours'
	];

	const TOKEN_TYPE_EMAIL_VERIFICATION = 'email_verification';
        const TOKEN_TYPE_RESET_PASSWORD = 'reset_password';
        
    /**
     * send authentication email with 1-time token
	 *
     * @param  array $params
     * @return void
     */
    public function sendAuthenticationEmail($params = []) {
		$email = $params['email'] ?? '';
		if (empty($email)) {
			throw new Portal_Exception('missing_parameter', '', 'Missing parameter: "email"');
		}
		
		$subject = $this->getEmailSubject('email_authentication');
                $token = $this->generateToken($params, self::TOKEN_TYPE_EMAIL_VERIFICATION);		
                $replaces = array_merge([
			'[[name]]' => $params['name'] ??  'Guest',
			'[[email_authentication_link]]' => 'http://billrun/callback?token=' . $token ?? '',//todo:: change to the real url
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
		$email = $params['email'] ?? '';
		if (empty($email)) {
			throw new Portal_Exception('missing_parameter', '', 'Missing parameter: "email"');
		}
		
		$subject = $this->getEmailSubject('reset_password');	
                $token = $this->generateToken($params, self::TOKEN_TYPE_RESET_PASSWORD);
                $replaces = array_merge([
			'[[name]]' => $params['name'] ??  'Guest',
			'[[reset_password_link]]' => 'http://billrun/callback?token=' . $token ?? '',//todo:: change to the real url
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
		$email = $params['email'] ?? '';
		if (empty($email)) {
			throw new Portal_Exception('missing_parameter', '', 'Missing parameter: "email"');
		}
                $username = $params['username'] ?? '';
                if (empty($username)) {
			throw new Portal_Exception('missing_parameter', '', 'Missing parameter: "username"');
		}
		$password = $params['password'] ?? '';
		if (empty($password)) {
			throw new Portal_Exception('missing_parameter', '', 'Missing parameter: "password"');
		}
		$subject = $this->getEmailSubject('welcome_account');
                $replaces = array_merge([
			'[[name]]' => $params['name'] ??  'Guest',
                        '[[username]]' => $username,
                        '[[password]]' => $password,
                        '[[access_from]]' => $params['access_from'] ?? 'now', //todo ::check from where need to take this param?? from api params? config? 
                        '[[link]]' =>  Billrun_Factory::config()->getConfigValue('tenant.website', '') //todo::verify this is right
                ], $this->BuildReplacesforCompanyInfo());
		$body = $this->getEmailBody('welcome_account', $replaces);
                
		if (!Billrun_Util::sendMail($subject, $body, [$email], [], true)) {
			$this->log("Portal_Actions_Registration::sendWelcomeEmail - failed to send Email to {$email}", Billrun_Log::ERR);
			throw new Portal_Exception('send_email_failed');
		}
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
			'id',
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
            '[[company_email]]' => Billrun_Factory::config()->getConfigValue('tenant.email', ''),
            '[[company_name]]' => Billrun_Factory::config()->getConfigValue('tenant.name', ''),
            '[[company_address]]' => Billrun_Factory::config()->getConfigValue('tenant.address', ''),
            '[[company_phone]]' => Billrun_Factory::config()->getConfigValue('tenant.phone', ''),
            '[[company_website]]' => Billrun_Factory::config()->getConfigValue('tenant.website', ''),  
            //maybe need to add Activity time for salt template?? 
        ];
    } 

}
