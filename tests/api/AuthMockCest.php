<?php

class AuthMockCest
{
    /**
     * @var object
     */
    protected $cacheSpy;

    /**
     * @var mixed
     */
    protected $originalCache;

    /**
     * Prepare mock state and replace the cache with a spy before each test.
     */
    public function _before(ApiTester $I)
    {
        $this->originalCache = $this->mockCache();
        $this->resetAuthMock();
    }

    /**
     * Restore original cache and reset mock state after each test.
     */
    public function _after(ApiTester $I)
    {
        $this->restoreCache();
        $this->resetAuthMock();
    }

    /**
     * Cache key is based on URL only by default (no payload hash).
     */
    public function tokenCachedByUrlOnly(ApiTester $I)
    {
        $this->resetAuthMock([
            'token_sequence' => [
                ['access_token' => 'T1', 'expires_in' => 30],
            ],
        ]);

        $this->sendApiRequest(['a' => 1], ['serialize_data_for_cache_key' => false]);
        $this->sendApiRequest(['a' => 2], ['serialize_data_for_cache_key' => false]);

        $stats = $this->getAuthStats();

        $I->assertCount(1, $this->cacheSpy->setCalls);
        $expectedKey = Billrun_Http_Authentication_Oauth2::class . "_access_token_" . md5(serialize(MOCKUP_URL . '/token'));
        $I->assertEquals($expectedKey, $this->cacheSpy->setCalls[0]['key']);
        $I->assertNotEquals($stats['api_calls'][0]['payload'], $stats['api_calls'][1]['payload']);
        $I->assertCount(1, $stats['token_calls']);
        $I->assertCount(2, $stats['api_calls']);
        $I->assertEquals('T1', $stats['api_calls'][0]['token']);
        $I->assertEquals('T1', $stats['api_calls'][1]['token']);
        $I->assertEquals(200, $stats['api_calls'][0]['status']);
        $I->assertEquals(200, $stats['api_calls'][1]['status']);
    }

    /**
     * Cache key includes payload when serialize_data_for_cache_key=true.
     */
    public function tokenCacheIncludesPayloadWhenEnabled(ApiTester $I)
    {
        $this->resetAuthMock([
            'token_sequence' => [
                ['access_token' => 'T1', 'expires_in' => 30],
                ['access_token' => 'T2', 'expires_in' => 30],
            ],
        ]);

        $this->sendApiRequest(['a' => 1], [
            'serialize_data_for_cache_key' => true,
            'data' => [
                'grant_type' => 'client_credentials',
                'client_id' => 'test',
                'client_secret' => 'secret',
                'scope' => 's1',
            ],
        ]);
        $this->sendApiRequest(['a' => 1], [
            'serialize_data_for_cache_key' => true,
            'data' => [
                'grant_type' => 'client_credentials',
                'client_id' => 'test',
                'client_secret' => 'secret',
                'scope' => 's2',
            ],
        ]);

        $stats = $this->getAuthStats();

        $I->assertCount(2, $stats['token_calls']);
        $I->assertCount(2, $stats['api_calls']);
        $I->assertEquals('T1', $stats['api_calls'][0]['token']);
        $I->assertEquals('T2', $stats['api_calls'][1]['token']);
    }

    /**
     * Cache TTL is expires_in minus 10 seconds.
     */
    public function cacheTtlReducedByTenSeconds(ApiTester $I)
    {
        $this->resetAuthMock([
            'token_sequence' => [
                ['access_token' => 'T1', 'expires_in' => 30],
            ],
        ]);

        $this->sendApiRequest(['a' => 1]);

        $lastSet = end($this->cacheSpy->setCalls);
        $I->assertEquals(20, $lastSet['ttl']);
    }

    /**
     * Cache TTL has a minimum of 10 seconds.
     */
    public function cacheTtlHasMinimum(ApiTester $I)
    {
        $this->resetAuthMock([
            'token_sequence' => [
                ['access_token' => 'T1', 'expires_in' => 8],
            ],
        ]);

        $this->sendApiRequest(['a' => 1]);

        $lastSet = end($this->cacheSpy->setCalls);
        $I->assertEquals(10, $lastSet['ttl']);
    }

    /**
     * 401 triggers token refresh and a single retry to 200.
     */
    public function retryAfter401Once(ApiTester $I)
    {
        $this->resetAuthMock([
            'token_sequence' => [
                ['access_token' => 'T1', 'expires_in' => 30],
                ['access_token' => 'T2', 'expires_in' => 30],
            ],
            'api_rules' => [
                ['token' => 'T1', 'status' => 401, 'body' => ['error' => 'unauthorized']],
                ['token' => 'T2', 'status' => 200, 'body' => ['ok' => true]],
            ],
        ]);

        $response = $this->sendApiRequest(['a' => 1]);
        $I->assertEquals(200, $response->getStatus());

        $stats = $this->getAuthStats();
        $I->assertCount(2, $stats['token_calls']);
        $I->assertCount(2, $stats['api_calls']);
        $I->assertEquals('T1', $stats['api_calls'][0]['token']);
        $I->assertEquals(401, $stats['api_calls'][0]['status']);
        $I->assertEquals('T2', $stats['api_calls'][1]['token']);
        $I->assertEquals(200, $stats['api_calls'][1]['status']);
    }

    /**
     * No retry is performed on non-401 responses.
     */
    public function noRetryOnNon401(ApiTester $I)
    {
        $this->resetAuthMock([
            'token_sequence' => [
                ['access_token' => 'T1', 'expires_in' => 30],
            ],
            'default_api_status' => 403,
            'default_api_body' => ['error' => 'forbidden'],
        ]);

        $response = $this->sendApiRequest(['a' => 1]);

        $I->assertEquals(403, $response->getStatus());

        $stats = $this->getAuthStats();
        $I->assertCount(1, $stats['token_calls']);
        $I->assertCount(1, $stats['api_calls']);
        $I->assertEquals(403, $stats['api_calls'][0]['status']);
    }

    /**
     * Retry is limited to a single attempt.
     */
    public function retryIsLimitedToSingleAttempt(ApiTester $I)
    {
        $this->resetAuthMock([
            'token_sequence' => [
                ['access_token' => 'T1', 'expires_in' => 30],
                ['access_token' => 'T2', 'expires_in' => 30],
            ],
            'api_rules' => [
                ['token' => 'T1', 'status' => 401],
                ['token' => 'T2', 'status' => 401],
            ],
        ]);

        $response = $this->sendApiRequest(['a' => 1]);
        $I->assertEquals(401, $response->getStatus());

        $stats = $this->getAuthStats();
        $I->assertCount(2, $stats['token_calls']);
        $I->assertCount(2, $stats['api_calls']);
    }

    /**
     * Different access_token_url results in a different cache key.
     */
    public function cacheKeyChangesWithDifferentAccessTokenUrl(ApiTester $I)
    {
        $this->resetAuthMock([
            'token_sequence' => [
                ['access_token' => 'T1', 'expires_in' => 30],
                ['access_token' => 'T2', 'expires_in' => 30],
            ],
        ]);

        $this->sendApiRequest(['a' => 1], [
            'access_token_url' => MOCKUP_URL . '/token',
        ]);
        $this->sendApiRequest(['a' => 2], [
            'access_token_url' => MOCKUP_URL . '/token?tenant=alt',
        ]);

        $I->assertCount(2, $this->cacheSpy->setCalls);
        $I->assertNotEquals($this->cacheSpy->setCalls[0]['key'], $this->cacheSpy->setCalls[1]['key']);

        $stats = $this->getAuthStats();
        $I->assertCount(2, $stats['token_calls']);
        $I->assertEquals('T1', $stats['api_calls'][0]['token']);
        $I->assertEquals('T2', $stats['api_calls'][1]['token']);
    }

    /**
     * Send a request to the mock API using OAuth2 authentication.
     */
    protected function sendApiRequest(array $payload, array $authOverrides = [])
    {
        $request = new Billrun_Http_Request(MOCKUP_URL . '/api', [
            'authentication' => array_merge([
                'type' => 'Oauth2',
                'access_token_url' => MOCKUP_URL . '/token',
                'data' => [
                    'grant_type' => 'client_credentials',
                    'client_id' => 'test',
                    'client_secret' => 'secret',
                ],
                'cache' => true,
            ], $authOverrides),
        ]);

        $request->setHeaders([
            'Content-Type' => 'application/json',
        ]);
        $request->setRawData(json_encode($payload));

        return $request->request(Zend_Http_Client::POST);
    }

    /**
     * Reset the auth mock server state with optional configuration.
     */
    protected function resetAuthMock(array $config = [])
    {
        $client = new Billrun_Http_Request(MOCKUP_URL . '/auth-mock/reset');
        $client->setHeaders(['Content-Type' => 'application/json']);
        $client->setRawData(json_encode($config));
        $client->request(Zend_Http_Client::POST);
    }

    /**
     * Fetch auth mock server stats for assertions.
     */
    protected function getAuthStats()
    {
        $client = new Billrun_Http_Request(MOCKUP_URL . '/auth-mock/stats');
        $response = $client->request(Zend_Http_Client::GET);
        return json_decode($response->getBody(), true);
    }

    /**
     * Replace Billrun cache with an in-memory spy to track set/remove calls.
     */
    protected function mockCache()
    {
        $spy = new class {
            public $store = [];
            public $setCalls = [];

            public function get($key)
            {
                return $this->store[$key] ?? false;
            }

            public function set($key, $value, $prefix = null, $lifetime = false)
            {
                $this->setCalls[] = ['key' => $key, 'value' => $value, 'ttl' => $lifetime];
                $this->store[$key] = $value;
                return $value;
            }

            public function remove($key)
            {
                $value = $this->store[$key] ?? false;
                unset($this->store[$key]);
                return $value;
            }
        };

        $ref = new ReflectionProperty('Billrun_Factory', 'cache');
        $ref->setAccessible(true);
        $prev = $ref->getValue();
        $ref->setValue(null, $spy);
        $this->cacheSpy = $spy;

        return $prev;
    }

    /**
     * Restore the original Billrun cache instance after tests.
     */
    protected function restoreCache()
    {
        $ref = new ReflectionProperty('Billrun_Factory', 'cache');
        $ref->setAccessible(true);
        $ref->setValue(null, $this->originalCache);
    }
}
