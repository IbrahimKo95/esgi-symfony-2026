<?php

namespace App\Repository;

use App\Entity\AdvisorAccess;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AdvisorAccess>
 */
class AdvisorAccessRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AdvisorAccess::class);
    }

    /**
     * @return AdvisorAccess[]
     */
    public function findForClient(User $client): array
    {
        return $this->createQueryBuilder('a')
            ->leftJoin('a.advisor', 'advisor')
            ->addSelect('advisor')
            ->where('a.client = :client')
            ->setParameter('client', $client)
            ->orderBy('a.grantedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return AdvisorAccess[]
     */
    public function findForAdvisor(User $advisor): array
    {
        return $this->createQueryBuilder('a')
            ->leftJoin('a.client', 'client')
            ->addSelect('client')
            ->where('a.advisor = :advisor')
            ->setParameter('advisor', $advisor)
            ->orderBy('a.status', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
