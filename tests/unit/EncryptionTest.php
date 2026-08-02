<?php

/**
 * Unit tests for Billrun_Utils_Encryption (deterministic 2-way field encryption)
 * and the Api_Translator_EncryptedModel write/query translator.
 */
class EncryptionTest extends \Codeception\Test\Unit
{
    /**
     * @var \UnitTester
     */
    protected $tester;

    /**
     * 64-char hex = 32 raw bytes for AES-256.
     */
    const TEST_KEY = '0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef';

    protected function _before()
    {
        putenv('BR_ENCRYPTION_KEY=' . self::TEST_KEY);
        $this->resetKeyCache();
    }

    protected function _after()
    {
        putenv('BR_ENCRYPTION_KEY');
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

    public function testRoundTrip()
    {
        $plain = 'sensitive-national-id-12345';
        $encrypted = Billrun_Utils_Encryption::encryptValue($plain);
        $this->assertNotEquals($plain, $encrypted);
        $this->assertTrue(Billrun_Utils_Encryption::isEncrypted($encrypted));
        $this->assertEquals($plain, Billrun_Utils_Encryption::decryptValue($encrypted));
    }

    public function testDeterministicCiphertext()
    {
        $plain = 'same-input';
        $a = Billrun_Utils_Encryption::encryptValue($plain);
        $b = Billrun_Utils_Encryption::encryptValue($plain);
        $this->assertEquals($a, $b, 'same plaintext must yield the same ciphertext (deterministic)');
        $this->assertNotEquals($a, Billrun_Utils_Encryption::encryptValue('different-input'));
    }

    public function testExactMatchSearchByReEncrypting()
    {
        // encrypting a search term reproduces the stored ciphertext, enabling equality queries
        $stored = Billrun_Utils_Encryption::encryptValue('lookup-me');
        $searchTerm = Billrun_Utils_Encryption::encryptValue('lookup-me');
        $this->assertEquals($stored, $searchTerm, 'search by encrypting the term must match the stored value');
    }

    public function testIdempotentEncrypt()
    {
        $plain = 'do-not-double-encrypt';
        $once = Billrun_Utils_Encryption::encryptValue($plain);
        $twice = Billrun_Utils_Encryption::encryptValue($once);
        $this->assertEquals($once, $twice, 'encrypting ciphertext should be a no-op');
        $this->assertEquals($plain, Billrun_Utils_Encryption::decryptValue($twice));
    }

    public function testIsEncrypted()
    {
        $this->assertTrue(Billrun_Utils_Encryption::isEncrypted(Billrun_Utils_Encryption::encryptValue('x')));
        $this->assertFalse(Billrun_Utils_Encryption::isEncrypted('plaintext'));
        $this->assertFalse(Billrun_Utils_Encryption::isEncrypted(123));
        $this->assertFalse(Billrun_Utils_Encryption::isEncrypted(null));
    }

    public function testEmptyAndNullPassThrough()
    {
        $this->assertSame('', Billrun_Utils_Encryption::encryptValue(''));
        $this->assertSame(null, Billrun_Utils_Encryption::encryptValue(null));
        $this->assertSame('', Billrun_Utils_Encryption::decryptValue(''));
        $this->assertSame(null, Billrun_Utils_Encryption::decryptValue(null));
    }

    public function testDecryptPlaintextReturnsAsIs()
    {
        $this->assertEquals('legacy-plaintext', Billrun_Utils_Encryption::decryptValue('legacy-plaintext'));
    }

    public function testTamperedCiphertextFailsOpen()
    {
        $encrypted = Billrun_Utils_Encryption::encryptValue('top-secret');
        // flip the last character to corrupt the ciphertext
        $tampered = substr($encrypted, 0, -1) . ($encrypted[strlen($encrypted) - 1] === 'A' ? 'B' : 'A');
        // must not throw on read; returns input unchanged (authentication mismatch)
        $result = Billrun_Utils_Encryption::decryptValue($tampered);
        $this->assertEquals($tampered, $result);
    }

    public function testWrongKeyFailsOpen()
    {
        $encrypted = Billrun_Utils_Encryption::encryptValue('top-secret');
        putenv('BR_ENCRYPTION_KEY=' . str_repeat('f', 64));
        $this->resetKeyCache();
        // different key -> authentication mismatch -> returns input unchanged, no throw
        $this->assertEquals($encrypted, Billrun_Utils_Encryption::decryptValue($encrypted));
    }

    public function testKeyNormalizationHex()
    {
        $this->assertRoundTripWithKey(self::TEST_KEY);
    }

    public function testKeyNormalizationBase64()
    {
        $this->assertRoundTripWithKey(base64_encode(random_bytes(32)));
    }

    public function testKeyNormalizationRaw32()
    {
        $this->assertRoundTripWithKey(str_repeat('k', 32));
    }

    public function testKeyNormalizationPassphrase()
    {
        $this->assertRoundTripWithKey('an-arbitrary-passphrase');
    }

    protected function assertRoundTripWithKey($key)
    {
        putenv('BR_ENCRYPTION_KEY=' . $key);
        $this->resetKeyCache();
        $plain = 'value-for-' . substr(md5($key), 0, 8);
        $encrypted = Billrun_Utils_Encryption::encryptValue($plain);
        $this->assertTrue(Billrun_Utils_Encryption::isEncrypted($encrypted));
        $this->assertEquals($plain, Billrun_Utils_Encryption::decryptValue($encrypted));
    }

    public function testTranslatorEncrypts()
    {
        $translator = new Api_Translator_EncryptedModel('secret', array());
        $translated = $translator->internalTranslateField('hello');
        $this->assertTrue(Billrun_Utils_Encryption::isEncrypted($translated));
        $this->assertEquals('hello', Billrun_Utils_Encryption::decryptValue($translated));
    }

    public function testTranslatorQueryMatchesStored()
    {
        // the same translator handles write and query; deterministic output makes them equal
        $translator = new Api_Translator_EncryptedModel('secret', array());
        $stored = $translator->internalTranslateField('find-me');
        $query = $translator->internalTranslateField('find-me');
        $this->assertEquals($stored, $query, 'query value must equal stored value for exact match');
    }

    public function testTranslatorIdempotentAndEmpty()
    {
        $translator = new Api_Translator_EncryptedModel('secret', array());
        $already = Billrun_Utils_Encryption::encryptValue('hi');
        $this->assertEquals($already, $translator->internalTranslateField($already));
        $this->assertSame('', $translator->internalTranslateField(''));
        $this->assertSame(null, $translator->internalTranslateField(null));
    }
}
