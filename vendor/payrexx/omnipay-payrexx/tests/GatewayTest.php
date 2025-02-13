<?php

namespace Omnipay\Payrexx\Test;

use Omnipay\Common\Exception\InvalidRequestException;
use Omnipay\Payrexx\Gateway;
use Omnipay\Payrexx\Message\Request\PurchaseRequest;
use Omnipay\Tests\GatewayTestCase;

/**
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 */
class GatewayTest extends GatewayTestCase
{
    /**
     * @var Gateway
     */
    protected $gateway;

    public function setUp()
    {
        parent::setUp();

        $this->gateway = new Gateway();
        $this->gateway->setApiKey('API_KEY');
        $this->gateway->setInstance('INSTANCE');
    }

    /**
     * @throws InvalidRequestException
     */
    public function testPurchase()
    {
        $request = $this->gateway->purchase([
            'amount' => '100',
            'currency' => 'CHF',
            'psp' => 36, // Payrexx Direct
            'skipResultPage' => true,
            'successRedirectUrl' => 'https://www.merchant-website.com/success',
            'failedRedirectUrl' => 'https://www.merchant-website.com/failed',
        ]);

        $this->assertInstanceOf(PurchaseRequest::class, $request);
        $this->assertSame('100', $request->getAmount());
        $this->assertSame('CHF', $request->getCurrency());
    }
}
