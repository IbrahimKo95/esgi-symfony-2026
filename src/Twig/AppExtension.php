<?php

namespace App\Twig;

use App\Entity\User;
use App\Repository\AnomalyRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class AppExtension extends AbstractExtension
{
    public function __construct(
        private readonly AnomalyRepository $anomalyRepository,
        private readonly Security $security,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('unread_anomaly_count', $this->getUnreadAnomalyCount(...)),
        ];
    }

    public function getUnreadAnomalyCount(): int
    {
        $user = $this->security->getUser();

        if (!$user instanceof User) {
            return 0;
        }

        return $this->anomalyRepository->countUnreadForUser($user);
    }
}
