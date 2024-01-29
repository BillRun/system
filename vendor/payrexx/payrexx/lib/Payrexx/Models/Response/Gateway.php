<?php

namespace Payrexx\Models\Response;

/**
 * Gateway response class
 *
 * @copyright   Payrexx AG
 * @author      Payrexx Development Team <info@payrexx.com>
 * @package     \Payrexx\Models\Response
 */
class Gateway extends \Payrexx\Models\Request\Gateway
{
    /** @var string */
    protected $hash;

    /** @var string */
    protected $link;

    /** @var string */
    protected $status;

    /** @var integer */
    protected $createdAt;

    /** @var array $invoices */
    protected $invoices;

    /** @var integer */
    protected $transactionId;

    /** @var string */
    protected $appLink;

    /**
     * @return string
     */
    public function getHash()
    {
        return $this->hash;
    }

    /**
     * @param string $hash
     */
    public function setHash($hash)
    {
        $this->hash = $hash;
    }

    /**
     * @return string
     */
    public function getLink()
    {
        return $this->link;
    }

    /**
     * @param string $link
     */
    public function setLink($link)
    {
        $this->link = $link;
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
     * @return integer
     */
    public function getCreatedAt()
    {
        return $this->createdAt;
    }

    /**
     * @param integer $createdAt
     */
    public function setCreatedAt($createdAt)
    {
        $this->createdAt = $createdAt;
    }

    /**
     * @param array $fields
     */
    public function setFields($fields)
    {
        $this->fields = $fields;
    }

    /**
     * @return array
     */
    public function getInvoices()
    {
        return $this->invoices;
    }

    /**
     * @param array $invoices
     */
    public function setInvoices($invoices)
    {
        $this->invoices = $invoices;
    }

    /**
     * @return integer|null
     */
    public function getTransactionId(): ?int
    {
        return $this->transactionId;
    }

    /**
     * @param integer $transactionId
     */
    public function setTransactionId(int $transactionId): void
    {
        $this->transactionId = $transactionId;
    }

    /**
     * @return string|null
     */
    public function getAppLink(): ?string
    {
        return $this->appLink;
    }

    /**
     * @param string $appLink
     */
    public function setAppLink(string $appLink): void
    {
        $this->appLink = $appLink;
    }
}
