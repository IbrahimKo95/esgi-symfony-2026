<?php

namespace App\Controller;

use App\Entity\RecurringTransaction;
use App\Entity\User;
use App\Form\RecurringTransactionType;
use App\Repository\RecurringTransactionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/recurring-transactions')]
#[IsGranted('ROLE_USER')]
class RecurringTransactionController extends AbstractController
{
    #[Route('', name: 'recurring_transaction_index', methods: ['GET'])]
    public function index(RecurringTransactionRepository $repository): Response
    {
        return $this->render('recurring_transaction/index.html.twig', [
            'recurringTransactions' => $repository->findBy(['author' => $this->getCurrentUser()], ['nextOccurrence' => 'ASC']),
        ]);
    }

    #[Route('/new', name: 'recurring_transaction_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $recurringTransaction = new RecurringTransaction();
        $recurringTransaction->setAuthor($this->getCurrentUser());

        $form = $this->createForm(RecurringTransactionType::class, $recurringTransaction);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($recurringTransaction);
            $entityManager->flush();

            $this->addFlash('success', 'Transaction recurrente creee avec succes.');

            return $this->redirectToRoute('recurring_transaction_index');
        }

        return $this->render('recurring_transaction/new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/{id}/edit', name: 'recurring_transaction_edit', methods: ['GET', 'POST'])]
    public function edit(RecurringTransaction $recurringTransaction, Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessAuthor($recurringTransaction);

        $form = $this->createForm(RecurringTransactionType::class, $recurringTransaction);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', 'Transaction recurrente modifiee avec succes.');

            return $this->redirectToRoute('recurring_transaction_index');
        }

        return $this->render('recurring_transaction/edit.html.twig', [
            'form' => $form,
            'recurringTransaction' => $recurringTransaction,
        ]);
    }

    #[Route('/{id}/delete', name: 'recurring_transaction_delete', methods: ['POST'])]
    public function delete(RecurringTransaction $recurringTransaction, Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessAuthor($recurringTransaction);

        if ($this->isCsrfTokenValid('delete-recurring-'.$recurringTransaction->getId(), $request->request->get('_token'))) {
            $entityManager->remove($recurringTransaction);
            $entityManager->flush();

            $this->addFlash('success', 'Transaction recurrente supprimee.');
        }

        return $this->redirectToRoute('recurring_transaction_index');
    }

    private function denyAccessUnlessAuthor(RecurringTransaction $recurringTransaction): void
    {
        if ($recurringTransaction->getAuthor() !== $this->getCurrentUser()) {
            throw $this->createAccessDeniedException();
        }
    }

    private function getCurrentUser(): User
    {
        /** @var User $user */
        $user = $this->getUser();

        return $user;
    }
}
