<?php

namespace Omnipay\Payrexx\Message\Response;
use Omnipay\Common\Message\AbstractResponse;
use Omnipay\Common\Message\RedirectResponseInterface;

/**
 * @see https://developers.payrexx.com/reference#gateway-1
 */
class CompletePurchaseResponse extends AbstractResponse implements RedirectResponseInterface
{
    /**
     * {@inheritdoc}
     */
    public function isSuccessful()
    {
        return $this->data->getStatus() === 'confirmed';
    }

    public function getTransactionReference()
    {
        return $this->data->getInvoices()[0]['transactions'][0]['id'] ?? null;
    }
}
