<?php

namespace App\Repository;

use App\Entity\Expense;
use App\Entity\Income;
use App\Entity\Transaction;
use App\Entity\Wallet;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Transaction>
 */
class TransactionRepository extends ServiceEntityRepository
{
    private const PER_PAGE = 15;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Transaction::class);
    }

    /**
     * Liste paginee et filtree des transactions d'un wallet.
     * Jointures sur category/author (ManyToOne) pour eviter le N+1 a l'affichage de la liste.
     *
     * @param array{type?: string, categoryId?: int, tagId?: int, from?: \DateTimeImmutable, to?: \DateTimeImmutable} $filters
     */
    public function findByWalletPaginated(Wallet $wallet, array $filters = [], int $page = 1): Paginator
    {
        $qb = $this->createQueryBuilder('t')
            ->leftJoin('t.category', 'c')
            ->addSelect('c')
            ->leftJoin('t.author', 'a')
            ->addSelect('a')
            ->leftJoin('t.tags', 'allTags')
            ->addSelect('allTags')
            ->where('t.wallet = :wallet')
            ->setParameter('wallet', $wallet)
            ->orderBy('t.date', 'DESC');

        if (!empty($filters['type'])) {
            $qb->andWhere('t INSTANCE OF :type')
                ->setParameter('type', 'income' === $filters['type'] ? Income::class : Expense::class);
        }

        if (!empty($filters['categoryId'])) {
            $qb->andWhere('c.id = :categoryId')
                ->setParameter('categoryId', $filters['categoryId']);
        }

        if (!empty($filters['tagId'])) {
            $qb->andWhere(':tagId MEMBER OF t.tags')
                ->setParameter('tagId', $filters['tagId']);
        }

        if (!empty($filters['from'])) {
            $qb->andWhere('t.date >= :from')
                ->setParameter('from', $filters['from']);
        }

        if (!empty($filters['to'])) {
            $qb->andWhere('t.date <= :to')
                ->setParameter('to', $filters['to']);
        }

        $qb->setFirstResult((max($page, 1) - 1) * self::PER_PAGE)
            ->setMaxResults(self::PER_PAGE);

        return new Paginator($qb->getQuery());
    }

    public static function getPerPage(): int
    {
        return self::PER_PAGE;
    }
}
