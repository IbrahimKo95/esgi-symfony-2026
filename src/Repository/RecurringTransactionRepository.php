<?php

namespace App\Repository;

use App\Entity\RecurringTransaction;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RecurringTransaction>
 */
class RecurringTransactionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RecurringTransaction::class);
    }

    /** @return RecurringTransaction[] */
    public function findDue(\DateTimeImmutable $asOf): array
    {
        return $this->createQueryBuilder('r')
            ->where('r.isActive = true')
            ->andWhere('r.nextOccurrence <= :today')
            ->setParameter('today', $asOf)
            ->getQuery()
            ->getResult();
    }
}
