<?php

/**
 * The PaymentProvider request model.
 *
 * @author    Payrexx Development <dev@payrexx.com>
 * @copyright 2018 Payrexx AG
 * @since     v1.0
 */

namespace Payrexx\Models\Request;

/**
 * Class PaymentProvider
 * @package Payrexx\Models\Request
 */
class PaymentProvider extends \Payrexx\Models\Base
{
    /** @var string $name */
    protected $name;

    /** @var array $paymentMethods */
    protected $paymentMethods;

    /** @var array $activePaymentMethods */
    protected $activePaymentMethods;

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param string $name
     */
    public function setName($name)
    {
        $this->name = $name;
    }

    /**
     * @return array
     */
    public function getPaymentMethods()
    {
        return $this->paymentMethods;
    }

    /**
     * @param array $paymentMethods
     */
    public function setPaymentMethods($paymentMethods)
    {
        $this->paymentMethods = $paymentMethods;
    }

    /**
     * @return array
     */
    public function getActivePaymentMethods()
    {
        return $this->activePaymentMethods;
    }

    /**
     * @param array $activePaymentMethods
     */
    public function setActivePaymentMethods($activePaymentMethods)
    {
        $this->activePaymentMethods = $activePaymentMethods;
    }

    /**
     * {@inheritdoc}
     */
    public function getResponseModel()
    {
        return new \Payrexx\Models\Response\PaymentProvider();
    }
}
