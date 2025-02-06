<?php
/**
 * The subscription request model
 * @author    Ueli Kramer <ueli.kramer@comvation.com>
 * @copyright 2014 Payrexx AG
 * @since     v1.0
 */
namespace Payrexx\Models\Request;

/**
 * Class Subscription
 * @package Payrexx\Models\Request
 */
class Subscription extends \Payrexx\Models\Base
{
    const CURRENCY_CHF = 'CHF';
    const CURRENCY_EUR = 'EUR';
    const CURRENCY_USD = 'USD';
    const CURRENCY_GBP = 'GBP';

    // all fields mandatory
    protected $userId = 0;
    protected $psp = 0;

    protected $purpose = '';
    protected $amount = 0;
    protected $currency = '';

    protected $paymentInterval = '';
    protected $period = '';
    protected $cancellationInterval = '';

    // optional
    protected $referenceId = '';

    /**
     * @return int
     */
    public function getUserId()
    {
        return $this->userId;
    }

    /**
     * @param int $userId
     */
    public function setUserId($userId)
    {
        $this->userId = $userId;
    }

    /**
     * @return int
     */
    public function getPsp()
    {
        return $this->psp;
    }

    /**
     * @param int $psp
     */
    public function setPsp($psp)
    {
        $this->psp = $psp;
    }

    /**
     * @return string
     */
    public function getPurpose()
    {
        return $this->purpose;
    }

    /**
     * @param string $purpose
     */
    public function setPurpose($purpose)
    {
        $this->purpose = $purpose;
    }

    /**
     * @return int
     */
    public function getAmount()
    {
        return $this->amount;
    }

    /**
     * @param int $amount
     */
    public function setAmount($amount)
    {
        $this->amount = $amount;
    }

    /**
     * @return string
     */
    public function getCurrency()
    {
        return $this->currency;
    }

    /**
     * @param string $currency
     */
    public function setCurrency($currency)
    {
        $this->currency = $currency;
    }

    /**
     * @return string
     */
    public function getPaymentInterval()
    {
        return $this->paymentInterval;
    }

    /**
     * @param string $paymentInterval
     */
    public function setPaymentInterval($paymentInterval)
    {
        $this->paymentInterval = $paymentInterval;
    }

    /**
     * @return string
     */
    public function getPeriod()
    {
        return $this->period;
    }

    /**
     * @param string $period
     */
    public function setPeriod($period)
    {
        $this->period = $period;
    }

    /**
     * @return string
     */
    public function getCancellationInterval()
    {
        return $this->cancellationInterval;
    }

    /**
     * @param string $cancellationInterval
     */
    public function setCancellationInterval($cancellationInterval)
    {
        $this->cancellationInterval = $cancellationInterval;
    }

    /**
     * @return string
     */
    public function getReferenceId()
    {
        return $this->referenceId;
    }

    /**
     * @param string $referenceId
     */
    public function setReferenceId($referenceId)
    {
        $this->referenceId = $referenceId;
    }

    /**
     * {@inheritdoc}
     */
    public function getResponseModel()
    {
        return new \Payrexx\Models\Response\Subscription();
    }
}
