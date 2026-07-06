<?php

namespace App\Service;

use App\Entity\Anomaly;
use App\Entity\Expense;
use App\Entity\Notification;
use App\Repository\BudgetRepository;
use App\Repository\TransactionRepository;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Events;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

/**
 * Applique les 3 regles de detection d'anomalies du cahier des charges a chaque
 * nouvelle depense (Expense), declenchee automatiquement via un listener Doctrine.
 */
#[AsEntityListener(event: Events::postPersist, entity: Expense::class)]
class AnomalyDetectionService
{
    private const CATEGORY_INCREASE_THRESHOLD = 0.5;

    public function __construct(
        private readonly TransactionRepository $transactionRepository,
        private readonly BudgetRepository $budgetRepository,
        private readonly MailerInterface $mailer,
    ) {
    }

    public function postPersist(Expense $expense, PostPersistEventArgs $args): void
    {
        $entityManager = $args->getObjectManager();

        $anomalies = array_filter([
            $this->checkSingleExpenseAnomaly($expense),
            $this->checkCategoryIncreaseAnomaly($expense),
            $this->checkBudgetRiskAnomaly($expense),
        ]);

        if ([] === $anomalies) {
            return;
        }

        foreach ($anomalies as $anomaly) {
            $entityManager->persist($anomaly);

            $notification = new Notification();
            $notification->setUser($anomaly->getUser());
            $notification->setAnomaly($anomaly);
            $notification->setChannel('in_app');
            $notification->setContent($anomaly->getMessage());
            $entityManager->persist($notification);
        }

        $entityManager->flush();

        foreach ($anomalies as $anomaly) {
            $this->sendEmailNotification($anomaly);
        }
    }

    /**
     * Regle 1 : la depense depasse moyenne + 2 ecarts-types des depenses
     * de l'utilisateur sur les 3 derniers mois.
     */
    private function checkSingleExpenseAnomaly(Expense $expense): ?Anomaly
    {
        $since = $expense->getDate()->modify('-3 months');
        $amounts = $this->transactionRepository->findExpenseAmountsForUserSince($expense->getAuthor(), $since, $expense->getId());

        if (count($amounts) < 3) {
            return null;
        }

        $mean = array_sum($amounts) / count($amounts);
        $variance = array_sum(array_map(static fn (float $a) => ($a - $mean) ** 2, $amounts)) / count($amounts);
        $threshold = $mean + 2 * sqrt($variance);
        $amount = (float) $expense->getAmount();

        if ($amount <= $threshold) {
            return null;
        }

        $anomaly = new Anomaly();
        $anomaly->setType('single_expense');
        $anomaly->setTransaction($expense);
        $anomaly->setUser($expense->getAuthor());
        $anomaly->setSeverity('warning');
        $anomaly->setMessage(sprintf(
            'Depense inhabituelle de %.2f %s sur "%s" (seuil habituel : %.2f).',
            $amount,
            $expense->getWallet()->getCurrency(),
            $expense->getCategory()->getName(),
            $threshold
        ));

        return $anomaly;
    }

    /**
     * Regle 2 : le total mensuel depense dans une categorie depasse la moyenne
     * des 3 derniers mois d'un seuil de +50%.
     */
    private function checkCategoryIncreaseAnomaly(Expense $expense): ?Anomaly
    {
        $user = $expense->getAuthor();
        $category = $expense->getCategory();
        $monthStart = new \DateTimeImmutable($expense->getDate()->format('Y-m-01'));
        $monthEnd = $monthStart->modify('+1 month');

        $currentTotal = $this->transactionRepository->sumExpensesForCategoryBetween($user, $category, $monthStart, $monthEnd);

        $previousTotals = [];
        for ($i = 1; $i <= 3; ++$i) {
            $start = $monthStart->modify(sprintf('-%d months', $i));
            $end = $start->modify('+1 month');
            $previousTotals[] = $this->transactionRepository->sumExpensesForCategoryBetween($user, $category, $start, $end);
        }

        $averagePrevious = array_sum($previousTotals) / count($previousTotals);

        if ($averagePrevious <= 0 || $currentTotal <= $averagePrevious * (1 + self::CATEGORY_INCREASE_THRESHOLD)) {
            return null;
        }

        $increasePercent = (($currentTotal - $averagePrevious) / $averagePrevious) * 100;

        $anomaly = new Anomaly();
        $anomaly->setType('category_increase');
        $anomaly->setCategory($category);
        $anomaly->setUser($user);
        $anomaly->setSeverity('warning');
        $anomaly->setMessage(sprintf(
            '+%.0f%% sur %s ce mois-ci par rapport a la moyenne des 3 derniers mois.',
            $increasePercent,
            $category->getName()
        ));

        return $anomaly;
    }

    /**
     * Regle 3 : projection lineaire de la depense actuelle sur le mois complet,
     * comparee au budget defini pour la categorie et le portefeuille.
     */
    private function checkBudgetRiskAnomaly(Expense $expense): ?Anomaly
    {
        $wallet = $expense->getWallet();
        $category = $expense->getCategory();
        $date = $expense->getDate();
        $monthStart = new \DateTimeImmutable($date->format('Y-m-01'));

        $budget = $this->budgetRepository->findOneForWalletCategoryMonth($wallet, $category, $monthStart);

        if (!$budget) {
            return null;
        }

        $monthEnd = $monthStart->modify('+1 month');
        $spent = $this->transactionRepository->sumExpensesForWalletCategoryBetween($wallet, $category, $monthStart, $monthEnd);

        $dayOfMonth = (int) $date->format('j');
        $daysInMonth = (int) $monthStart->format('t');
        $projection = $spent / $dayOfMonth * $daysInMonth;
        $budgetAmount = (float) $budget->getAmount();

        if ($projection <= $budgetAmount) {
            return null;
        }

        $anomaly = new Anomaly();
        $anomaly->setType('budget_risk');
        $anomaly->setCategory($category);
        $anomaly->setUser($expense->getAuthor());
        $anomaly->setSeverity('critical');
        $anomaly->setMessage(sprintf(
            'Projection de %.2f %s sur "%s" ce mois-ci, au-dela du budget de %.2f %s.',
            $projection,
            $wallet->getCurrency(),
            $category->getName(),
            $budgetAmount,
            $wallet->getCurrency()
        ));

        return $anomaly;
    }

    private function sendEmailNotification(Anomaly $anomaly): void
    {
        $email = (new Email())
            ->from('no-reply@expensemanager.local')
            ->to($anomaly->getUser()->getEmail())
            ->subject('Anomalie detectee - ExpenseManager')
            ->text($anomaly->getMessage());

        $this->mailer->send($email);
    }
}
