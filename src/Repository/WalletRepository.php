<?php

namespace App\Repository;

use App\Entity\AdvisorAccess;
use App\Entity\User;
use App\Entity\Wallet;
use App\Entity\WalletMember;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Wallet>
 */
class WalletRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Wallet::class);
    }

    /**
     * @return Wallet[]
     */
    public function findForUser(User $user): array
    {
        return $this->createQueryBuilder('w')
            ->leftJoin('w.owner', 'owner')
            ->addSelect('owner')
            ->leftJoin(WalletMember::class, 'wm', 'WITH', 'wm.wallet = w')
            ->where('w.owner = :user')
            ->orWhere('wm.user = :user')
            ->setParameter('user', $user)
            ->orderBy('w.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function isAccessibleByUser(Wallet $wallet, User $user): bool
    {
        if ($wallet->getOwner() === $user) {
            return true;
        }

        if ($this->getEntityManager()->getRepository(WalletMember::class)->count(['wallet' => $wallet, 'user' => $user]) > 0) {
            return true;
        }

        $access = $this->getEntityManager()->getRepository(AdvisorAccess::class)->findOneBy([
            'advisor' => $user,
            'client' => $wallet->getOwner(),
        ]);

        return null !== $access && 'active' === $access->getStatus();
    }
}
