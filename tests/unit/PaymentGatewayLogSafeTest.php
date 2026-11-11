<?php

/**
 * Unit tests for Billrun_PaymentGateway::logSafe (BRCD-5542): the gateway's
 * secret fields (getSecretFields) and card tokens must reach the log 2-way
 * encrypted, never in plaintext, while the rest of the payload stays readable.
 */

/**
 * Test double: skips the real constructor (Omnipay, account, ini load) and
 * returns fixed credentials instead of reading tenant config.
 */
class LogSafeTestGateway extends Billrun_PaymentGateway_CreditGuard
{
    public $credentials = [
        'user' => 'merchant_login',
        'password' => 'S3cr3tPass!',
        'mid' => 123,
        'endpoint_url' => 'https://cg.example',
    ];

    public function __construct() {}

    public function getGatewayCredentials()
    {
        return $this->credentials;
    }
}

class PaymentGatewayLogSafeTest extends \Codeception\Test\Unit
{
    /**
     * @var \UnitTester
     */
    protected $tester;

    /**
     * 64-char hex = 32 raw bytes for AES-256.
     */
    const TEST_KEY = '0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef';

    const CARD_TOKEN = '4580111122223333';

    /**
     * @var LogSafeTestGateway
     */
    protected $gateway;

    /**
     * Tenant config 'encryption.key' as it was before the test, restored in _after.
     */
    protected $originalConfigKey;

    protected function _before()
    {
        putenv('BR_ENCRYPTION_KEY=' . self::TEST_KEY);
        $this->resetKeyCache();
        $this->originalConfigKey = Billrun_Factory::config()->getConfigValue(Billrun_Utils_Encryption::CONFIG_KEY_PATH, null);
        $this->gateway = new LogSafeTestGateway();
    }

    protected function _after()
    {
        putenv('BR_ENCRYPTION_KEY');
        Billrun_Factory::config()->setConfigValue(Billrun_Utils_Encryption::CONFIG_KEY_PATH, $this->originalConfigKey);
        $this->resetKeyCache();
    }

    /**
     * The key is statically cached; clear it so each test/key takes effect.
     */
    protected function resetKeyCache()
    {
        $ref = new ReflectionProperty('Billrun_Utils_Encryption', 'cachedKey');
        $ref->setAccessible(true);
        $ref->setValue(null, null);
    }

    /**
     * Regex fragment matching the encrypted-value marker, taken from the util so
     * a future scheme version (prefix change) does not break these tests.
     */
    protected function encPrefix()
    {
        return preg_quote(Billrun_Utils_Encryption::ENC_PREFIX, '/');
    }

    /**
     * A CreditGuard-shaped request: credentials at top level, card token inside the XML.
     */
    protected function samplePayload()
    {
        return [
            'user' => 'merchant_login',
            'password' => 'S3cr3tPass!',
            'int_in' => '<ashrait><cardId>' . self::CARD_TOKEN . '</cardId><mid>123</mid></ashrait>',
        ];
    }

    public function testArrayPayloadEncryptsCredentialsAndKeepsOtherFields()
    {
        $out = $this->gateway->logSafe($this->samplePayload());

        $this->assertStringNotContainsString('S3cr3tPass!', $out);
        $this->assertMatchesRegularExpression('/\[password\] => ' . $this->encPrefix() . '/', $out);
        // only getSecretFields() values are encrypted; user and mid stay readable
        $this->assertStringContainsString('[user] => merchant_login', $out);
        $this->assertStringContainsString('<mid>123</mid>', $out);
    }

    public function testOutputDecryptsBackToOriginal()
    {
        $out = $this->gateway->logSafe($this->samplePayload());

        $this->assertEquals(1, preg_match('/\[password\] => (' . $this->encPrefix() . '[^\s]+)/', $out, $m), 'encrypted password not found in output');
        $this->assertEquals('S3cr3tPass!', Billrun_Utils_Encryption::decryptValue($m[1]), 'log value must be 2-way decryptable');
    }

    public function testExtraSecretInsideXmlString()
    {
        $out = $this->gateway->logSafe($this->samplePayload(), [self::CARD_TOKEN]);

        $this->assertStringNotContainsString(self::CARD_TOKEN, $out);
        $this->assertMatchesRegularExpression('/<cardId>' . $this->encPrefix() . '[^<]+<\/cardId><mid>123<\/mid>/', $out, 'XML around the token must survive');
    }

    public function testUrlEncodedPayload()
    {
        $payload = http_build_query(['user' => 'merchant_login', 'password' => 'S3cr3tPass!']);
        $this->assertStringContainsString('S3cr3tPass%21', $payload, 'precondition: http_build_query encodes the !');

        $out = $this->gateway->logSafe($payload);

        $this->assertStringNotContainsString('S3cr3tPass%21', $out);
        $this->assertStringNotContainsString('S3cr3tPass!', $out);
        $this->assertMatchesRegularExpression('/^user=merchant_login&password=' . $this->encPrefix() . '/', $out);
    }

    public function testJsonPayloadWithEscapedChars()
    {
        $this->gateway->credentials['password'] = 'pa/ss"wd';
        $payload = json_encode(['Login' => 'merchant_login', 'Password' => 'pa/ss"wd']);
        $this->assertStringContainsString('pa\/ss\"wd', $payload, 'precondition: json_encode escapes / and "');

        $out = $this->gateway->logSafe($payload);

        $this->assertStringNotContainsString('pa\/ss\"wd', $out);
        $this->assertStringNotContainsString('pa/ss"wd', $out);
        $this->assertMatchesRegularExpression('/"Password":"' . $this->encPrefix() . '/', $out);
    }

    public function testEmptyAndNullExtrasAreIgnored()
    {
        $this->gateway->credentials = [];
        $payload = ['terminal' => '0962832', 'amount' => 100];

        $out = $this->gateway->logSafe($payload, [null, '']);

        $this->assertEquals(print_r($payload, 1), $out, 'payload without secrets must be rendered unchanged');
    }

    public function testMasksWhenNoKeyConfigured()
    {
        putenv('BR_ENCRYPTION_KEY');
        Billrun_Factory::config()->setConfigValue(Billrun_Utils_Encryption::CONFIG_KEY_PATH, '');
        $this->resetKeyCache();

        $out = $this->gateway->logSafe($this->samplePayload());

        $this->assertStringNotContainsString('S3cr3tPass!', $out);
        $this->assertMatchesRegularExpression('/\[password\] => \*\*\*/', $out, 'without a key the value must be masked, not leaked');
    }
}
