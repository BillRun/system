<?php

/**
 * Imap extends functional login with oauth2 base on zend-mail of zend framework 1
 * 
 * <code>
 * <?php
 * $email               = 'your_gmail@gmail.com';
 * $$oauth2AccessToken  = 'oauth2_access_token of your_gmail@gmail.com';
 * $popGmailHost        = 'pop.gmail.com';
 * $pop365Host          = 'outlook.office365.com';
 * $pop3Protocol        = new Zend_Mail_Protocol_Pop3Oauth2($popGmailHost, $port = '995', $ssl = true);
 * 
 * if (!$pop3Protocol->login($email, $oauth2AccessToken)) {
 *      throw new \DomainException('Invalid email or oauth2 access token expired');
 * }
 * $index  = 0;
 * $max    = 10;
 * $mail   = new Zend_Mail_Storage_Pop3($pop3Protocol);
 * echo $mail->countMessages();
 * foreach($mail as $messageNum => $message) {
 *     echo $message->subject;
 *     if ($max === ++$index) {
 *         break;
 *     }
 * }
 * >?
 * </code>
 */
class Zend_Mail_Protocol_Pop3Oauth2 extends Zend_Mail_Protocol_Pop3
{
    protected $host;

    /**
     * Public constructor
     *
     * @param  string      $host  hostname or IP address of POP3 server, if given connect() is called
     * @param  int|null    $port  port of POP3 server, null for default (110 or 995 for ssl)
     * @param  bool|string $ssl   use ssl? 'SSL', 'TLS' or false
     * @throws Zend_Mail_Protocol_Exception
     */
    public function __construct($host = '', $port = null, $ssl = false)
    {
        $this->host = $host;
        parent::__construct($host, $port, $ssl);
    }
    /**
     * Authenticate an IMAP, POP or SMTP connection using OAuth2
     * Support php ver >= php5.6
     *
     * @see Guide authenticate oauth2 integrate MS office 365 - https://docs.microsoft.com/en-us/exchange/client-developer/legacy-protocols/how-to-authenticate-an-imap-pop-smtp-application-by-using-oauth\
     * @see Guide authenticate oauth2 integrate Gmail         - https://developers.google.com/gmail/imap/xoauth2-protocol
     * @see imap Oauth2 authenticate request & response spec  - https://developers.google.com/gmail/imap/xoauth2-protocol#error_response_2
     *
     * @param string $email
     * @param string $accessToken
     * @param bool $tryApop - keep to compatible with Zend_Mail_Protocol_Pop3::login only - dont use this param
     * @throw Zend_Mail_Protocol_Exception
     * @return bool true if login success else false
     */
    public function login($email, $accessToken, $tryApop = true)
    {
        $xoauth2Command = "AUTH XOAUTH2";
        $credential = base64_encode("user=" . $email . "\1auth=Bearer " . $accessToken . "\1\1"); //https://docs.microsoft.com/vi-vn/exchange/client-developer/legacy-protocols/how-to-authenticate-an-imap-pop-smtp-application-by-using-oauth#sasl-xoauth2
        $host = strtolower($this->host);
        if ('outlook.office365.com' === $host) {
            //https://docs.microsoft.com/en-us/exchange/client-developer/legacy-protocols/how-to-authenticate-an-imap-pop-smtp-application-by-using-oauth#pop-protocol-exchange
            $this->sendRequest($xoauth2Command);
            $response = $this->readResponseWithoutException();
            $this->sendRequest($credential);
            $response = $this->readResponseWithoutException();
            $isLoginSuccess = preg_match('/successfully authenticated/i', $response);
            if ($isLoginSuccess) {
                return true;
            }
            $isLoginFailure = preg_match('/Authentication failure:/i', $response);
            if ($isLoginFailure) {
                return false;
            }
            throw new Zend_Mail_Protocol_Exception("POP3 - XOauth2 login $email failed. $host response \"$response\"");
        }

        //https://developers.google.com/gmail/imap/xoauth2-protocol#pop_protocol_exchange
        $loginCommand   = "$xoauth2Command $credential";
        $this->sendRequest($loginCommand);
        $response = $this->readResponseWithoutException();
        $isLoginSuccess = preg_match('/^Welcome/i', $response);
        if ($isLoginSuccess) {
            return true;
        }
        $authFailedReason = base64_decode($response, true);
        $authFailedReason = false === $authFailedReason ? $response : $authFailedReason;
        throw new Zend_Mail_Protocol_Exception("POP3 - XOauth2 login $email failed. {$this->host} response: \"$authFailedReason\" ");
    }

    /**
     * read a response
     *
     * @param  boolean $multiline response has multiple lines and should be read until "<nl>.<nl>"
     * @return string response
     * @throws Zend_Mail_Protocol_Exception
     */
    protected function readResponseWithoutException($multiline = false)
    {
        $result = @fgets($this->_socket);
        if (!is_string($result)) {
            /**
             * @see Zend_Mail_Protocol_Exception
             */
            // require_once 'Zend/Mail/Protocol/Exception.php';
            throw new Zend_Mail_Protocol_Exception('read failed - connection closed?');
        }

        $result = trim($result);
        if (strpos($result, ' ')) {
            list($status, $message) = explode(' ', $result, 2);
        } else {
            $status = $result;
            $message = '';
        }

        if ($multiline) {
            $message = '';
            $line = fgets($this->_socket);
            while ($line && rtrim($line, "\r\n") != '.') {
                if ($line[0] == '.') {
                    $line = substr($line, 1);
                }
                $message .= $line;
                $line = fgets($this->_socket);
            };
        }

        return $message;
    }
}
