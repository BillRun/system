<?php
/**
 * Oauth2 authentication support for imap protocol
 *
 * <code>
 * <?php
 * $email               = 'your_gmail@gmail.com';
 * $oauth2AccessToken   = 'oauth2_access_token of your_gmail@gmail.com';
 * $imapGmailHost       = 'imap.gmail.com';
 * $imap365Host         = 'outlook.office365.com';
 * $imapProtocol        = new Zend_Mail_Protocol_ImapOauth2($imapGmailHost, $port = '993', $ssl = true);
 * 
 * if (!$imapProtocol->login($email, $oauth2AccessToken)) {     
 *      throw new \DomainException('Invalid email or oauth2 access token expired');
 * }
 * $index  = 0;
 * $max    = 10;
 * $mail   = new Zend_Mail_Storage_Imap($imapProtocol);
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


/**
 * @category   Zend
 * @package    Zend_Mail
 * @subpackage Protocol
 */
class Zend_Mail_Protocol_ImapOauth2 extends Zend_Mail_Protocol_Imap
{
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
     * @return bool true if login success else false
     * @throws \Zend_Mail_Protocol_Exception
     */
    public function login($email, $accessToken)
    {
        $authenticateParams = array(
            'XOAUTH2',
            base64_encode("user=" . $email . "\1auth=Bearer " . $accessToken . "\1\1")
        );
        $this->sendRequest('AUTHENTICATE', $authenticateParams);
        while (true) {
            $response   = "";
            $isPlus     = $this->readLine($response, '+', true);
            if ($isPlus) {
                $this->sendRequest('');
                continue;
            }
            $isResponseLoginFailed =
                preg_match('/^NO /i', $response) ||
                preg_match('/^BAD /i', $response);
            if ($isResponseLoginFailed) {
                return false;
            }
            $isResponseLoginSuccess = preg_match("/^OK /i", $response);
            if ($isResponseLoginSuccess) {
                return true;
            }
            //Some untagged response, such as CAPABILITY then read next line to make sure login OK or NO|BAD
        }
        throw new Zend_Mail_Protocol_Exception("IMAP - XOauth2 login $email failed. $this->host response \"$response\"");
    }

}