<?php

class ZendSmtpOauth2Test extends \Codeception\Test\Unit
{
    /**
     * @var \UnitTester
     */
    protected $tester;

    protected function _before()
    {
    }

    protected function _after()
    {
    }

    public function testInitialization()
    {
        $config = [
            'email'       => 'test@example.com',
            'accessToken' => 'fake-token',
        ];
        $instance = new Zend_Mail_Protocol_Smtp_Auth_Oauth2('127.0.0.1', null, $config);
        $this->assertInstanceOf(Zend_Mail_Protocol_Smtp_Auth_Oauth2::class, $instance);
    }
}
