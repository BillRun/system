<?php

/**
 * <code>
 * <?php
 *  $office365Host      = 'smtp.office365.com';
 *  $gmailHost          = 'smtp.gmail.com';
 *  $mailSender         = 'mail_sender@gmail.com';
 *  $oauth2AccessToken  = 'access token of mail_sender@gmail.com';
 *  $transport          = new Zend_Mail_Transport_Smtp($gmailHost, [
 *      'ssl'               => 'tls',
 *      'port'              => 587,
 *      'auth'              => 'oauth2', //Zend_Mail_Protocol_Smtp_Auth_Oauth2
 *      'email'             => $mailSender,
 *      'accessToken'       => $oauth2AccessToken,
 *  ]);
 *
 *  Zend_Mail::setDefaultTransport($transport);
 *  Zend_Mail::setDefaultFrom($mailSender, 'your_full_name_here');
 *  Zend_Mail::setDefaultReplyTo($mailSender,'your_full_name_here');
 *
 *  $mail = new Zend_Mail();
 *  $date = date('Y-m-d H:i:s P');
 *  $mail
 *      ->addTo($mailTo)
 *      ->setSubject("Test xoauh2 - smtp $date")
 *      ->setBodyText("$date : smtp + xoauth2 send mail test");
 *
 *  $mail->send();
 * ?>
 * </code>
 */
class Zend_Mail_Protocol_Smtp_Auth_Oauth2 extends Zend_Mail_Protocol_Smtp
{
    /**
     * LOGIN email
     *
     * @var string
     */
    protected $email;


    /**
     * Oauth2 access token
     *
     * @var string
     */
    protected $accessToken;


    /**
     * Constructor.
     *
     * @param  string $host   (Default: 127.0.0.1)
     * @param  int    $port   (Default: null)
     * @param  array  $config (Required: ['email' => 'email-logged@account', 'accessToken' => 'oauth2 access token']
     * @return void
     */
    public function __construct($host = '127.0.0.1', $port = null, $config = null)
    {
        if (!isset($config['email'])) {
            throw new InvalidArgumentException("Please provide login email in \$config['email']");
        }
        if (!isset($config['accessToken'])) {
            throw new InvalidArgumentException("Please provide oauth2 access token in \$config['accessToken']");
        }
        $this->email        = $config['email'];
        $this->accessToken  = $config['accessToken'];
        parent::__construct($host, $port, $config);
    }

    /**
     * Perform LOGIN XOAUTH2 authentication with supplied credentials
     * Throws a Zend_Mail_Protocol_Exception if an unexpected code is returned.
     * @return void
     */
    public function auth()
    {
        // Ensure AUTH has not already been initiated.
        parent::auth();
        $authenticationSucceededResponseCode = 235;
        $xoauth2 = base64_encode("user=" . $this->email . "\1auth=Bearer " . $this->accessToken . "\1\1");
        $this->_send("AUTH XOAUTH2 {$xoauth2}");
        $this->_expect($authenticationSucceededResponseCode);
        $this->_auth = true;
    }
}
