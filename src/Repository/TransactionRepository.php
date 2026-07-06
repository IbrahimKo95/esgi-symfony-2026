<?php

namespace App\Repository;

use App\Entity\Category;
use App\Entity\Expense;
use App\Entity\Income;
use App\Entity\Transaction;
use App\Entity\User;
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

    /**
     * Montants des depenses de l'utilisateur depuis une date, hors transaction exclue.
     * Utilise par la regle "depense ponctuelle anormale" (moyenne + 2 ecarts-types).
     *
     * @return float[]
     */
    public function findExpenseAmountsForUserSince(User $user, \DateTimeImmutable $since, ?int $excludeTransactionId = null): array
    {
        $qb = $this->createQueryBuilder('t')
            ->select('t.amount')
            ->where('t INSTANCE OF '.Expense::class)
            ->andWhere('t.author = :user')
            ->andWhere('t.date >= :since')
            ->setParameter('user', $user)
            ->setParameter('since', $since);

        if (null !== $excludeTransactionId) {
            $qb->andWhere('t.id != :excludeId')
                ->setParameter('excludeId', $excludeTransactionId);
        }

        return array_map(static fn (array $row) => (float) $row['amount'], $qb->getQuery()->getResult());
    }

    /**
     * Total des depenses d'un utilisateur pour une categorie donnee, sur une periode.
     */
    public function sumExpensesForCategoryBetween(User $user, Category $category, \DateTimeImmutable $from, \DateTimeImmutable $to, ?int $excludeTransactionId = null): float
    {
        $qb = $this->createQueryBuilder('t')
            ->select('COALESCE(SUM(t.amount), 0) as total')
            ->where('t INSTANCE OF '.Expense::class)
            ->andWhere('t.author = :user')
            ->andWhere('t.category = :category')
            ->andWhere('t.date >= :from')
            ->andWhere('t.date < :to')
            ->setParameter('user', $user)
            ->setParameter('category', $category)
            ->setParameter('from', $from)
            ->setParameter('to', $to);

        if (null !== $excludeTransactionId) {
            $qb->andWhere('t.id != :excludeId')
                ->setParameter('excludeId', $excludeTransactionId);
        }

        return (float) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Total des depenses d'un wallet pour une categorie donnee, sur une periode.
     * Utilise par la regle de risque de depassement de budget (le budget est defini par wallet+categorie+mois).
     */
    public function sumExpensesForWalletCategoryBetween(Wallet $wallet, Category $category, \DateTimeImmutable $from, \DateTimeImmutable $to): float
    {
        return (float) $this->createQueryBuilder('t')
            ->select('COALESCE(SUM(t.amount), 0) as total')
            ->where('t INSTANCE OF '.Expense::class)
            ->andWhere('t.wallet = :wallet')
            ->andWhere('t.category = :category')
            ->andWhere('t.date >= :from')
            ->andWhere('t.date < :to')
            ->setParameter('wallet', $wallet)
            ->setParameter('category', $category)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Agregation mensuelle (depenses/revenus) sur les N derniers mois pour le dashboard.
     * Le regroupement par mois se fait cote PHP (volume de donnees faible pour ce cas d'usage),
     * pour eviter une fonction CAST/SUBSTRING non portable en DQL.
     *
     * @return array<int, array{month: string, type: string, total: float}>
     */
    public function findMonthlyTotalsForUser(User $user, \DateTimeImmutable $since): array
    {
        $totals = [];

        foreach (['expense' => Expense::class, 'income' => Income::class] as $type => $class) {
            $rows = $this->createQueryBuilder('t')
                ->select('t.date as date, t.amount as amount')
                ->where('t INSTANCE OF '.$class)
                ->andWhere('t.author = :user')
                ->andWhere('t.date >= :since')
                ->setParameter('user', $user)
                ->setParameter('since', $since)
                ->getQuery()
                ->getResult();

            foreach ($rows as $row) {
                /** @var \DateTimeImmutable $date */
                $date = $row['date'];
                $key = $date->format('Y-m').'|'.$type;
                $totals[$key] = ($totals[$key] ?? 0.0) + (float) $row['amount'];
            }
        }

        $result = [];
        foreach ($totals as $key => $total) {
            [$month, $type] = explode('|', $key);
            $result[] = ['month' => $month, 'type' => $type, 'total' => $total];
        }

        usort($result, static fn (array $a, array $b) => $a['month'] <=> $b['month']);

        return $result;
    }

    /**
     * Repartition des depenses par categorie sur une periode, pour le dashboard.
     *
     * @return array<int, array{category: string, total: float}>
     */
    public function findCategoryBreakdownForUser(User $user, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $rows = $this->createQueryBuilder('t')
            ->select('c.name as category, COALESCE(SUM(t.amount), 0) as total')
            ->join('t.category', 'c')
            ->where('t INSTANCE OF '.Expense::class)
            ->andWhere('t.author = :user')
            ->andWhere('t.date >= :from')
            ->andWhere('t.date < :to')
            ->setParameter('user', $user)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->groupBy('c.name')
            ->orderBy('total', 'DESC')
            ->getQuery()
            ->getResult();

        return array_map(static fn (array $row) => [
            'category' => $row['category'],
            'total' => (float) $row['total'],
        ], $rows);
    }
}
