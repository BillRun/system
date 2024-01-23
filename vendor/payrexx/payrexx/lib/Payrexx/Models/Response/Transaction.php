<?php

/**
 * Transaction response model
 *
 * @copyright   Payrexx AG
 * @author      Payrexx Development Team <info@payrexx.com>
 */
namespace Payrexx\Models\Response;

/**
 * Transaction class
 *
 * @package Payrexx\Models\Response
 */
class Transaction extends \Payrexx\Models\Request\Transaction
{

    private $time;
    private $status;
    private $lang;
    private $psp;
    private $pspId;
    private $mode;
    private $payment;
    private $invoice;
    private $contact;
    private $pageUuid;
    private $payrexxFee;
    private $fee;
    private $refundable;
    private $partiallyRefundable;

    const CONFIRMED = 'confirmed';
    const INITIATED = 'initiated';
    const WAITING = 'waiting';
    const AUTHORIZED = 'authorized';
    const RESERVED = 'reserved';
    const CANCELLED = 'cancelled';
    const REFUNDED = 'refunded';
    const DISPUTED = 'disputed';
    const DECLINED = 'declined';
    const ERROR = 'error';
    const EXPIRED = 'expired';
    const PARTIALLY_REFUNDED = 'partially-refunded';
    const REFUND_PENDING = 'refund_pending';
    const INSECURE = 'insecure';
    const UNCAPTURED = 'uncaptured';

    /**
     * @access  public
     * @param   string  $uuid
     */
    public function setUuid($uuid)
    {
        $this->uuid = $uuid;
    }

    /**
     * @access  public
     * @return  string
     */
    public function getUuid()
    {
        return $this->uuid;
    }

    /**
     * @access  public
     * @param   string  $time
     */
    public function setTime($time)
    {
        $this->time = $time;
    }

    /**
     * @access  public
     * @return  string
     */
    public function getTime()
    {
        return $this->time;
    }

    /**
     * @access  public
     * @param   string  $status
     */
    public function setStatus($status)
    {
        $this->status = $status;
    }

    /**
     * @access  public
     * @return  string
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * @access  public
     * @param   string  $lang
     */
    public function setLang($lang)
    {
        $this->lang = $lang;
    }

    /**
     * @access  public
     * @return  string
     */
    public function getLang()
    {
        return $this->lang;
    }

    /**
     * @access  public
     * @param   string  $psp
     */
    public function setPsp($psp)
    {
        $this->psp = $psp;
    }

    /**
     * @access  public
     * @return  string
     */
    public function getPsp()
    {
        return $this->psp;
    }

    /**
     * @return int
     */
    public function getPspId()
    {
        return $this->pspId;
    }

    /**
     * @param int $pspId
     */
    public function setPspId($pspId)
    {
        $this->pspId = $pspId;
    }

    /**
     * @return mixed
     */
    public function getMode()
    {
        return $this->mode;
    }

    /**
     * @param int $mode
     */
    public function setMode($mode)
    {
        $this->mode = $mode;
    }

    /**
     * @access  public
     * @param   array  $payment
     */
    public function setPayment($payment)
    {
        $this->payment = $payment;
    }

    /**
     * @access  public
     * @return  array
     */
    public function getPayment()
    {
        return $this->payment;
    }

    /**
     * @return array
     */
    public function getInvoice()
    {
        return $this->invoice;
    }

    /**
     * @param array $invoice
     */
    public function setInvoice($invoice)
    {
        $this->invoice = $invoice;
    }

    /**
     * @return array
     */
    public function getContact()
    {
        return $this->contact;
    }

    /**
     * @param array $contact
     */
    public function setContact($contact)
    {
        $this->contact = $contact;
    }

    /**
     * @return string
     */
    public function getPageUuid()
    {
        return $this->pageUuid;
    }

    /**
     * @param string $pageUuid
     */
    public function setPageUuid($pageUuid)
    {
        $this->pageUuid = $pageUuid;
    }

    /**
     * @return integer
     */
    public function getPayrexxFee()
    {
        return $this->payrexxFee;
    }

    /**
     * @param int $payrexxFee
     */
    public function setPayrexxFee(int $payrexxFee)
    {
        $this->payrexxFee = $payrexxFee;
    }

    /**
     * @return integer
     */
    public function getFee()
    {
        return $this->fee;
    }

    /**
     * @param int $fee
     */
    public function setFee(int $fee)
    {
        $this->fee = $fee;
    }

    /**
     * Supported since version 1.2
     * @return bool|null
     */
    public function getRefundable()
    {
        return $this->refundable;
    }

    /**
     * @param mixed $refundable
     */
    public function setRefundable($refundable)
    {
        $this->refundable = $refundable;
    }

    /**
     * Supported since version 1.2
     * @return bool|null
     */
    public function getPartiallyRefundable()
    {
        return $this->partiallyRefundable;
    }

    /**
     * @param mixed $partiallyRefundable
     */
    public function setPartiallyRefundable($partiallyRefundable)
    {
        $this->partiallyRefundable = $partiallyRefundable;
    }
}
