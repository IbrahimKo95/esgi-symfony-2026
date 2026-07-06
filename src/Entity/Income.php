<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity]
class Income extends Transaction
{
    #[ORM\Column(length: 100, nullable: true)]
    #[Groups(['transaction:read', 'transaction:write'])]
    private ?string $source = null;

    public function getType(): string
    {
        return 'income';
    }

    public function getSource(): ?string
    {
        return $this->source;
    }

    public function setSource(?string $source): static
    {
        $this->source = $source;

        return $this;
    }
}
