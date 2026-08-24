<?php

namespace Payrexx\Models\Request;

use Payrexx\Models\Base;

class Payout extends Base
{
    protected string $currency;

    protected int $amount;

    protected int $pspId;

    protected ?string $statementDescriptor;

    /**
     * @return string
     */
    public function getCurrency(): string
    {
        return $this->currency;
    }

    /**
     * @param string $currency
     */
    public function setCurrency(string $currency): void
    {
        $this->currency = $currency;
    }

    /**
     * @return int
     */
    public function getAmount(): int
    {
        return $this->amount;
    }

    /**
     * @param int $amount
     */
    public function setAmount(int $amount): void
    {
        $this->amount = $amount;
    }

    /**
     * @return int
     */
    public function getPspId(): int
    {
        return $this->pspId;
    }

    /**
     * @param int $pspId
     */
    public function setPspId(int $pspId): void
    {
        $this->pspId = $pspId;
    }

    /**
     * @return string|null
     */
    public function getStatementDescriptor(): ?string
    {
        return $this->statementDescriptor;
    }

    /**
     * @param string|null $statementDescriptor
     */
    public function setStatementDescriptor(?string $statementDescriptor): void
    {
        $this->statementDescriptor = $statementDescriptor;
    }

    public function getResponseModel(): \Payrexx\Models\Response\Payout
    {
        return new \Payrexx\Models\Response\Payout();
    }
}
