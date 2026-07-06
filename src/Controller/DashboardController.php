<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\AnomalyRepository;
use App\Repository\TransactionRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/dashboard')]
#[IsGranted('ROLE_USER')]
class DashboardController extends AbstractController
{
    #[Route('', name: 'dashboard_index', methods: ['GET'])]
    public function index(TransactionRepository $transactionRepository, AnomalyRepository $anomalyRepository): Response
    {
        $user = $this->getCurrentUser();

        $monthStart = new \DateTimeImmutable('first day of this month');
        $monthEnd = $monthStart->modify('+1 month');
        $sixMonthsAgo = $monthStart->modify('-5 months');

        $categoryBreakdown = $transactionRepository->findCategoryBreakdownForUser($user, $monthStart, $monthEnd);
        $monthlyTotals = $transactionRepository->findMonthlyTotalsForUser($user, $sixMonthsAgo);

        $months = [];
        $cursor = $sixMonthsAgo;
        while ($cursor <= $monthStart) {
            $months[] = $cursor->format('Y-m');
            $cursor = $cursor->modify('+1 month');
        }

        $expenseByMonth = array_fill_keys($months, 0.0);
        $incomeByMonth = array_fill_keys($months, 0.0);
        foreach ($monthlyTotals as $row) {
            if (!isset($expenseByMonth[$row['month']])) {
                continue;
            }
            if ('expense' === $row['type']) {
                $expenseByMonth[$row['month']] = $row['total'];
            } else {
                $incomeByMonth[$row['month']] = $row['total'];
            }
        }

        $activeAnomalies = $anomalyRepository->findBy(['user' => $user, 'isRead' => false], ['detectedAt' => 'DESC'], 5);

        return $this->render('dashboard/index.html.twig', [
            'categoryLabels' => array_column($categoryBreakdown, 'category'),
            'categoryTotals' => array_column($categoryBreakdown, 'total'),
            'months' => $months,
            'expenseByMonth' => array_values($expenseByMonth),
            'incomeByMonth' => array_values($incomeByMonth),
            'activeAnomalies' => $activeAnomalies,
        ]);
    }

    private function getCurrentUser(): User
    {
        /** @var User $user */
        $user = $this->getUser();

        return $user;
    }
}
