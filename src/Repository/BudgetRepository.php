<?php

namespace App\Repository;

use App\Entity\Budget;
use App\Entity\Category;
use App\Entity\User;
use App\Entity\Wallet;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Budget>
 */
class BudgetRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Budget::class);
    }

    /**
     * @return Budget[]
     */
    public function findForUser(User $user): array
    {
        return $this->createQueryBuilder('b')
            ->leftJoin('b.category', 'c')
            ->addSelect('c')
            ->leftJoin('b.wallet', 'w')
            ->addSelect('w')
            ->where('b.owner = :user')
            ->setParameter('user', $user)
            ->orderBy('b.month', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findOneForWalletCategoryMonth(Wallet $wallet, Category $category, \DateTimeImmutable $month): ?Budget
    {
        return $this->createQueryBuilder('b')
            ->where('b.wallet = :wallet')
            ->andWhere('b.category = :category')
            ->andWhere('b.month = :month')
            ->setParameter('wallet', $wallet)
            ->setParameter('category', $category)
            ->setParameter('month', $month, 'date_immutable')
            ->getQuery()
            ->getOneOrNullResult();
    }
}
