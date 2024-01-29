<?php

namespace Omnipay\Payrexx\Message\Response;

use Omnipay\Common\Message\AbstractResponse;

/**
 * @see https://developers.payrexx.com/reference#refund-a-transaction
 */
class RefundResponse extends AbstractResponse
{
    /**
     * @return bool
     */
    public function isSuccessful()
    {
        return !empty($this->data->getId());
    }
}
