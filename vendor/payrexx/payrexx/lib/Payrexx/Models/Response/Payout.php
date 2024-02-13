<?php

namespace Payrexx\Models\Response;

class Payout extends \Payrexx\Models\Request\Payout
{

    /** @var string */
    protected $object = '';

    /** @var int */
    protected $amount = 0;

    /** @var int */
    protected $totalFees = 0;

    /** @var string */
    protected $currency = '';

    /** @var string */
    protected $date = '';

    /** @var string */
    protected $statement = '';

    /** @var string */
    protected $status = '';

    /** @var array */
    protected $destination = [];

    /** @var array */
    protected $transfers = [];

    /** @var array */
    protected $merchant = [];

    /**
     * @return string
     */
    public function getObject()
    {
        return $this->object;
    }

    /**
     * @param string $object
     */
    public function setObject($object)
    {
        $this->object = $object;
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
     * @return int
     */
    public function getTotalFees()
    {
        return $this->totalFees;
    }

    /**
     * @param int $totalFees
     */
    public function setTotalFees($totalFees)
    {
        $this->totalFees = $totalFees;
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
    public function getDate()
    {
        return $this->date;
    }

    /**
     * @param string $date
     */
    public function setDate($date)
    {
        $this->date = $date;
    }

    /**
     * @return string
     */
    public function getStatement()
    {
        return $this->statement;
    }

    /**
     * @param string $statement
     */
    public function setStatement($statement)
    {
        $this->statement = $statement;
    }

    /**
     * @return string
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * @param string $status
     */
    public function setStatus($status)
    {
        $this->status = $status;
    }

    /**
     * @return array
     */
    public function getDestination()
    {
        return $this->destination;
    }

    /**
     * @param array $destination
     */
    public function setDestination($destination)
    {
        $this->destination = $destination;
    }

    /**
     * @return array
     */
    public function getTransfers()
    {
        return $this->transfers;
    }

    /**
     * @param array $transfers
     */
    public function setTransfers($transfers)
    {
        $this->transfers = $transfers;
    }

    /**
     * @return array
     */
    public function getMerchant()
    {
        return $this->merchant;
    }

    /**
     * @param array $merchant
     */
    public function setMerchant($merchant)
    {
        $this->merchant = $merchant;
    }
}
