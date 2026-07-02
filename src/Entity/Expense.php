<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class Expense extends Transaction
{
    #[ORM\Column(options: ['default' => false])]
    private bool $isRecurring = false;

    #[ORM\ManyToOne(targetEntity: RecurringTransaction::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?RecurringTransaction $recurringTransaction = null;

    public function isRecurring(): bool
    {
        return $this->isRecurring;
    }

    public function setIsRecurring(bool $isRecurring): static
    {
        $this->isRecurring = $isRecurring;

        return $this;
    }

    public function getRecurringTransaction(): ?RecurringTransaction
    {
        return $this->recurringTransaction;
    }

    public function setRecurringTransaction(?RecurringTransaction $recurringTransaction): static
    {
        $this->recurringTransaction = $recurringTransaction;

        return $this;
    }
}
