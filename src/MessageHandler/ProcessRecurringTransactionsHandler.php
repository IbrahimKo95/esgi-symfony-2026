<?php

namespace App\MessageHandler;

use App\Entity\Expense;
use App\Entity\Income;
use App\Entity\RecurringTransaction;
use App\Message\ProcessRecurringTransactionsMessage;
use App\Repository\RecurringTransactionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class ProcessRecurringTransactionsHandler
{
    public function __construct(
        private readonly RecurringTransactionRepository $recurringRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(ProcessRecurringTransactionsMessage $message): void
    {
        $today = new \DateTimeImmutable('today');
        $due = $this->recurringRepository->findDue($today);

        foreach ($due as $recurring) {
            $this->generateTransaction($recurring);
            $recurring->setNextOccurrence($this->computeNextOccurrence($recurring));
        }

        $this->entityManager->flush();
    }

    private function generateTransaction(RecurringTransaction $recurring): void
    {
        $transaction = 'income' === $recurring->getType() ? new Income() : new Expense();
        $transaction->setAmount((float) $recurring->getAmount());
        $transaction->setDescription($recurring->getDescription() ?? $recurring->getCategory()->getName());
        $transaction->setCategory($recurring->getCategory());
        $transaction->setWallet($recurring->getWallet());
        $transaction->setAuthor($recurring->getAuthor());
        $transaction->setDate($recurring->getNextOccurrence());
        $transaction->setIsRecurring(true);
        $transaction->setRecurringTransaction($recurring);

        $this->entityManager->persist($transaction);
    }

    private function computeNextOccurrence(RecurringTransaction $recurring): \DateTimeImmutable
    {
        return match ($recurring->getFrequency()) {
            'weekly' => $recurring->getNextOccurrence()->modify('+7 days'),
            'yearly' => $recurring->getNextOccurrence()->modify('+1 year'),
            default => $recurring->getNextOccurrence()->modify('+1 month'),
        };
    }
}
