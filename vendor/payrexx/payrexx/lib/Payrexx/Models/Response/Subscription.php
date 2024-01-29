<?php
/**
 * The subscription response model
 * @author    Ueli Kramer <ueli.kramer@comvation.com>
 * @copyright 2014 Payrexx AG
 * @since     v1.0
 */
namespace Payrexx\Models\Response;

/**
 * Class Subscription
 * @package Payrexx\Models\Response
 */
class Subscription extends \Payrexx\Models\Request\Subscription
{
    protected $status = '';
    protected $pspSubscriptionId = '';
    protected $start = '';
    protected $end = '';
    protected $cancelledDate = 0;
    protected $firstCancelDate = 0;
    protected $nextPayDate = 0;

    /**
     * @return int
     */
    public function getCancelledDate()
    {
        return $this->cancelledDate;
    }

    /**
     * @param int $cancelledDate
     */
    public function setCancelledDate($cancelledDate)
    {
        $this->cancelledDate = $cancelledDate;
    }

    /**
     * @return string
     */
    public function getEnd()
    {
        return $this->end;
    }

    /**
     * @param string $end
     */
    public function setEnd($end)
    {
        $this->end = $end;
    }

    /**
     * @return int
     */
    public function getFirstCancelDate()
    {
        return $this->firstCancelDate;
    }

    /**
     * @param int $firstCancelDate
     */
    public function setFirstCancelDate($firstCancelDate)
    {
        $this->firstCancelDate = $firstCancelDate;
    }

    /**
     * @return int
     */
    public function getNextPayDate()
    {
        return $this->nextPayDate;
    }

    /**
     * @param int $nextPayDate
     */
    public function setNextPayDate($nextPayDate)
    {
        $this->nextPayDate = $nextPayDate;
    }
    
    /**
     * @return string
     */
    public function getPspSubscriptionId()
    {
        return $this->pspSubscriptionId;
    }

    /**
     * @param string $pspSubscriptionId
     */
    public function setPspSubscriptionId($pspSubscriptionId)
    {
        $this->pspSubscriptionId = $pspSubscriptionId;
    }

    /**
     * @return string
     */
    public function getStart()
    {
        return $this->start;
    }

    /**
     * @param string $start
     */
    public function setStart($start)
    {
        $this->start = $start;
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
    

}
