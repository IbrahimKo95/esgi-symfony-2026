<?php

namespace App\Security\Voter;

use App\Entity\Transaction;
use App\Entity\User;
use App\Entity\WalletMember;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Autorise la consultation/modification d'une transaction a son auteur,
 * ou au proprietaire/contributeur du portefeuille concerne.
 */
class TransactionVoter extends Voter
{
    public const VIEW = 'TRANSACTION_VIEW';
    public const EDIT = 'TRANSACTION_EDIT';

    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $subject instanceof Transaction && in_array($attribute, [self::VIEW, self::EDIT], true);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            return false;
        }

        /** @var Transaction $transaction */
        $transaction = $subject;
        $wallet = $transaction->getWallet();

        if ($transaction->getAuthor() === $user || $wallet->getOwner() === $user) {
            return true;
        }

        $member = $this->entityManager->getRepository(WalletMember::class)->findOneBy([
            'wallet' => $wallet,
            'user' => $user,
        ]);

        if (!$member) {
            return false;
        }

        return match ($attribute) {
            self::VIEW => true, // owner/contributor/viewer peuvent tous consulter
            self::EDIT => 'contributor' === $member->getRole(),
            default => false,
        };
    }
}
