<?php

namespace App\Repository;

use App\Entity\AdvisorAccess;
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
}
