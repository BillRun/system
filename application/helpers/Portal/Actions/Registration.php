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
		'default' => '24 hours',
		'email_verification' => '1 hour',
	];

	const TOKEN_TYPE_EMAIL_VERIFICATION = 'email_verification';
        
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
		
		$subject = $this->getAuthenticationEmailSubject();
		$bodyParams = [
			'token' => $this->generateToken($params, self::TOKEN_TYPE_EMAIL_VERIFICATION),
			'name' => $params['name'] ?? 'Guest',
		];
		$body = $this->getAuthenticationEmailBody($bodyParams);
		if (!Billrun_Util::sendMail($subject, $body, [$email], [], true)) {
			$this->log("Portal_Actions_Registration::sendAuthenticationEmail - failed to send Email to {$email}", Billrun_Log::ERR);
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
	 * get the subject of the Email send for authentication
	 *
	 * @return string
	 */
	protected function getAuthenticationEmailSubject() {
		return Billrun_Factory::config()->getConfigValue('email_templates.email_authentication.subject', '');
	}
		
	/**
	 * get the body of the Email send for authentication
	 *
	 * @param  array $params
	 * @return string
	 */
	protected function getAuthenticationEmailBody($params = []) {
		$body = Billrun_Factory::config()->getConfigValue('email_templates.email_authentication.content', '');
		$replaces = [
			'[[name]]' => $params['name'] ?? '',
			'[[token]]' => $params['token'] ?? '',
			'[[company_email]]' => Billrun_Factory::config()->getConfigValue('tenant.email', ''),
			'[[company_name]]' => Billrun_Factory::config()->getConfigValue('tenant.name', ''),
		];
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
		$secret = 'K#PgCwg}#?mB>/`[w{z"~u#>&@y]X_)V+,vz7,7K';
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
	 * Authenticate the request.
	 * all registration actions does not require authentication
	 *
	 * @return boolean
	 */
    protected function authenticate($params = []) {
		return true;
	}

}
