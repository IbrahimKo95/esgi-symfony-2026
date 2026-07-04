<?php

namespace App\Security\Voter;

use App\Entity\AdvisorAccess;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Un conseiller (ROLE_ADVISOR) ne peut consulter (jamais modifier) les donnees
 * d'un client que si un acces actif existe. Tout acces revoque coupe immediatement
 * la lecture. Le sujet vote est le client (User) dont on veut consulter les donnees.
 */
class AdvisorAccessVoter extends Voter
{
    public const VIEW_CLIENT_DATA = 'VIEW_CLIENT_DATA';

    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return self::VIEW_CLIENT_DATA === $attribute && $subject instanceof User;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $advisor = $token->getUser();

        if (!$advisor instanceof User || !in_array('ROLE_ADVISOR', $advisor->getRoles(), true)) {
            return false;
        }

        /** @var User $client */
        $client = $subject;

        $access = $this->entityManager->getRepository(AdvisorAccess::class)->findOneBy([
            'client' => $client,
            'advisor' => $advisor,
        ]);

        return null !== $access && 'active' === $access->getStatus();
    }
}
