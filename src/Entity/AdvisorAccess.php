<?php

namespace App\Entity;

use App\Repository\AdvisorAccessRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: AdvisorAccessRepository::class)]
#[ORM\UniqueConstraint(name: 'advisor_access_unique', columns: ['client_id', 'advisor_id'])]
class AdvisorAccess
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'client_id', nullable: false)]
    private ?User $client = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'advisor_id', nullable: false)]
    private ?User $advisor = null;

    #[ORM\Column(length: 10)]
    #[Assert\Choice(['pending', 'active', 'revoked'])]
    private ?string $status = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $grantedAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $revokedAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getClient(): ?User
    {
        return $this->client;
    }

    public function setClient(?User $client): static
    {
        $this->client = $client;

        return $this;
    }

    public function getAdvisor(): ?User
    {
        return $this->advisor;
    }

    public function setAdvisor(?User $advisor): static
    {
        $this->advisor = $advisor;

        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getGrantedAt(): ?\DateTimeImmutable
    {
        return $this->grantedAt;
    }

    public function setGrantedAt(?\DateTimeImmutable $grantedAt): static
    {
        $this->grantedAt = $grantedAt;

        return $this;
    }

    public function getRevokedAt(): ?\DateTimeImmutable
    {
        return $this->revokedAt;
    }

    public function setRevokedAt(?\DateTimeImmutable $revokedAt): static
    {
        $this->revokedAt = $revokedAt;

        return $this;
    }
}
