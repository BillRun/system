<?php

namespace Payrexx\Models\Request;

use Payrexx\Models\Base;

class Payout extends Base
{
    protected string $currency;

    protected float $amount;

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
     * @return float
     */
    public function getAmount(): float
    {
        return $this->amount;
    }

    /**
     * @param float $amount
     */
    public function setAmount(float $amount): void
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
