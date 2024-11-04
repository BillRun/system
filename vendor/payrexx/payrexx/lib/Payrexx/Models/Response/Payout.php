<?php

namespace Payrexx\Models\Response;

class Payout extends \Payrexx\Models\Request\Payout
{

    /** @var string */
    protected string $object = '';

    /** @var float */
    protected float $amount = 0;

    /** @var float */
    protected float $totalFees = 0;

    /** @var ?string */
    protected ?string $date = '';

    /** @var ?string */
    protected ?string $statement = '';

    /** @var ?string */
    protected ?string $status = '';

    /** @var ?array */
    protected ?array $destination = [];

    /** @var ?array */
    protected ?array $transfers = [];

    /** @var ?array */
    protected ?array $merchant = [];

    /**
     * @return string
     */
    public function getObject(): string
    {
        return $this->object;
    }

    /**
     * @param string $object
     */
    public function setObject(string $object): void
    {
        $this->object = $object;
    }

    /**
     * @return float
     */
    public function getTotalFees(): float
    {
        return $this->totalFees;
    }

    /**
     * @param float $totalFees
     */
    public function setTotalFees(float $totalFees): void
    {
        $this->totalFees = $totalFees;
    }

    /**
     * @return ?string
     */
    public function getDate(): ?string
    {
        return $this->date;
    }

    /**
     * @param ?string $date
     */
    public function setDate(?string $date): void
    {
        $this->date = $date;
    }

    /**
     * @return ?string
     */
    public function getStatement(): ?string
    {
        return $this->statement;
    }

    /**
     * @param ?string $statement
     */
    public function setStatement(?string $statement): void
    {
        $this->statement = $statement;
    }

    /**
     * @return ?string
     */
    public function getStatus(): ?string
    {
        return $this->status;
    }

    /**
     * @param ?string $status
     */
    public function setStatus(?string $status): void
    {
        $this->status = $status;
    }

    /**
     * @return ?array
     */
    public function getDestination(): ?array
    {
        return $this->destination;
    }

    /**
     * @param ?array $destination
     */
    public function setDestination(?array $destination): void
    {
        $this->destination = $destination;
    }

    /**
     * @return ?array
     */
    public function getTransfers(): ?array
    {
        return $this->transfers;
    }

    /**
     * @param ?array $transfers
     */
    public function setTransfers(?array $transfers): void
    {
        $this->transfers = $transfers;
    }

    /**
     * @return array
     */
    public function getMerchant(): array
    {
        return $this->merchant;
    }

    /**
     * @param ?array $merchant
     */
    public function setMerchant(?array $merchant): void
    {
        $this->merchant = $merchant;
    }
}
