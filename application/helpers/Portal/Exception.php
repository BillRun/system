<?php

use Exception;

/**
 * Class 
 */
class Portal_Exception extends Exception {

    protected $error;

    const ERROR_CODES = [
        'general_error' => [
            'code' => 3000,
            'desc' => 'General error',
        ],
        'permission_denied' => [
            'code' => 3001,
            'desc' => 'Permission Denied',
        ],
        'authorization_failed' => [
            'code' => 3002,
            'desc' => 'Authorization Failed',
        ],
        'authentication_failed' => [
            'code' => 3003,
            'desc' => 'Authentication Failed',
        ],
        'no_account' => [
            'code' => 4001,
            'desc' => 'No account found',
        ],
        'missing_parameter' => [
            'code' => 4501,
            'desc' => 'Missing parameter/s',
        ],
        'account_get_failure' => [
            'code' => 4510,
            'desc' => 'Failed to get account',
        ],
        'account_update_failure' => [
            'code' => 4511,
            'desc' => 'Failed to update account',
        ],
        'subscriber_update_failure' => [
            'code' => 4512,
            'desc' => 'Failed to update subscriber',
        ],
        'send_email_failed' => [
            'code' => 4601,
            'desc' => 'Failed to send Email',
        ],
    ];

    public function __construct($error = '', $code = '', $desc = '') {
        $this->error = $error;
        $errorCodeData = self::ERROR_CODES[$this->error] ?: self::ERROR_CODES['general_error'];

        if (empty($code)) {
            $code = $errorCodeData['code'];
        }

        if (empty($desc)) {
            $desc = $errorCodeData['desc'];
        }

        parent::__construct($desc, $code);
    }

    public function getError() {
        return $this->error;
    }

}
