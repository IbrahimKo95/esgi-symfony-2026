<?php

namespace App\Repository;

use App\Entity\Wallet;
use App\Entity\WalletMember;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<WalletMember>
 */
class WalletMemberRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WalletMember::class);
    }

    /**
     * @return WalletMember[]
     */
    public function findByWallet(Wallet $wallet): array
    {
        return $this->createQueryBuilder('wm')
            ->leftJoin('wm.user', 'u')
            ->addSelect('u')
            ->where('wm.wallet = :wallet')
            ->setParameter('wallet', $wallet)
            ->orderBy('wm.joinedAt', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
