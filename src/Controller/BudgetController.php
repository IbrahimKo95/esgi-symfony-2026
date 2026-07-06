<?php

namespace App\Controller;

use App\Entity\Budget;
use App\Entity\User;
use App\Form\BudgetType;
use App\Repository\BudgetRepository;
use App\Repository\TransactionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/budgets')]
#[IsGranted('ROLE_USER')]
class BudgetController extends AbstractController
{
    #[Route('', name: 'budget_index', methods: ['GET'])]
    public function index(BudgetRepository $budgetRepository, TransactionRepository $transactionRepository): Response
    {
        $budgets = $budgetRepository->findForUser($this->getCurrentUser());

        $rows = array_map(function (Budget $budget) use ($transactionRepository) {
            $monthStart = $budget->getMonth();
            $monthEnd = $monthStart->modify('+1 month');
            $spent = $transactionRepository->sumExpensesForWalletCategoryBetween($budget->getWallet(), $budget->getCategory(), $monthStart, $monthEnd);

            return [
                'budget' => $budget,
                'spent' => $spent,
                'percent' => $budget->getAmount() > 0 ? min(100, (int) round($spent / (float) $budget->getAmount() * 100)) : 0,
            ];
        }, $budgets);

        return $this->render('budget/index.html.twig', [
            'rows' => $rows,
        ]);
    }

    #[Route('/new', name: 'budget_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, BudgetRepository $budgetRepository): Response
    {
        $budget = new Budget();
        $budget->setOwner($this->getCurrentUser());

        $form = $this->createForm(BudgetType::class, $budget);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $existing = $budgetRepository->findOneForWalletCategoryMonth($budget->getWallet(), $budget->getCategory(), $budget->getMonth());

            if ($existing) {
                $this->addFlash('error', 'Un budget existe deja pour ce portefeuille, cette categorie et ce mois.');
            } else {
                $entityManager->persist($budget);
                $entityManager->flush();

                $this->addFlash('success', 'Budget cree avec succes.');

                return $this->redirectToRoute('budget_index');
            }
        }

        return $this->render('budget/new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/{id}/delete', name: 'budget_delete', methods: ['POST'])]
    public function delete(Budget $budget, Request $request, EntityManagerInterface $entityManager): Response
    {
        if ($budget->getOwner() !== $this->getCurrentUser()) {
            throw $this->createAccessDeniedException();
        }

        if ($this->isCsrfTokenValid('delete-budget-'.$budget->getId(), $request->request->get('_token'))) {
            $entityManager->remove($budget);
            $entityManager->flush();

            $this->addFlash('success', 'Budget supprime.');
        }

        return $this->redirectToRoute('budget_index');
    }

    private function getCurrentUser(): User
    {
        /** @var User $user */
        $user = $this->getUser();

        return $user;
    }
}
