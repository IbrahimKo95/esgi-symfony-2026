<?php

namespace App\Controller;

use App\Entity\Comment;
use App\Entity\Expense;
use App\Entity\Income;
use App\Entity\Transaction;
use App\Entity\User;
use App\Entity\Wallet;
use App\Form\TransactionType;
use App\Repository\CategoryRepository;
use App\Repository\CommentRepository;
use App\Repository\TagRepository;
use App\Repository\TransactionRepository;
use App\Repository\WalletRepository;
use App\Security\Voter\TransactionVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class TransactionController extends AbstractController
{
    #[Route('/wallets/{walletId}/transactions', name: 'transaction_index', methods: ['GET'])]
    public function index(
        int $walletId,
        Request $request,
        WalletRepository $walletRepository,
        TransactionRepository $transactionRepository,
        CategoryRepository $categoryRepository,
        TagRepository $tagRepository,
    ): Response {
        $wallet = $this->findWalletOrFail($walletId, $walletRepository);
        $this->denyAccessUnlessWalletAccessible($wallet, $walletRepository);

        $filters = [
            'type' => $request->query->get('type') ?: null,
            'categoryId' => $request->query->get('categoryId') ? $request->query->getInt('categoryId') : null,
            'tagId' => $request->query->get('tagId') ? $request->query->getInt('tagId') : null,
            'from' => $request->query->get('from') ? new \DateTimeImmutable($request->query->get('from')) : null,
            'to' => $request->query->get('to') ? new \DateTimeImmutable($request->query->get('to')) : null,
        ];
        $page = max(1, $request->query->getInt('page', 1));

        $paginator = $transactionRepository->findByWalletPaginated($wallet, $filters, $page);

        return $this->render('transaction/index.html.twig', [
            'wallet' => $wallet,
            'transactions' => $paginator,
            'total' => count($paginator),
            'page' => $page,
            'perPage' => TransactionRepository::getPerPage(),
            'filters' => $filters,
            'categories' => $categoryRepository->findAvailableForUser($this->getCurrentUser()),
            'tags' => $tagRepository->findBy(['owner' => $this->getCurrentUser()], ['name' => 'ASC']),
        ]);
    }

    #[Route('/wallets/{walletId}/transactions/new/{type}', name: 'transaction_new', methods: ['GET', 'POST'], requirements: ['type' => 'expense|income'])]
    public function new(
        int $walletId,
        string $type,
        Request $request,
        WalletRepository $walletRepository,
        EntityManagerInterface $entityManager,
    ): Response {
        $wallet = $this->findWalletOrFail($walletId, $walletRepository);
        $this->denyAccessUnlessWalletAccessible($wallet, $walletRepository);

        $transaction = 'income' === $type ? new Income() : new Expense();
        $transaction->setWallet($wallet);
        $transaction->setAuthor($this->getCurrentUser());
        $transaction->setDate(new \DateTimeImmutable());

        $form = $this->createForm(TransactionType::class, $transaction);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($transaction);
            $entityManager->flush();

            $this->addFlash('success', 'Transaction enregistree avec succes.');

            return $this->redirectToRoute('transaction_index', ['walletId' => $wallet->getId()]);
        }

        return $this->render('transaction/new.html.twig', [
            'form' => $form,
            'wallet' => $wallet,
            'type' => $type,
        ]);
    }

    #[Route('/transactions/{id}', name: 'transaction_show', methods: ['GET'])]
    public function show(Transaction $transaction, CommentRepository $commentRepository): Response
    {
        $this->denyAccessUnlessGranted(TransactionVoter::VIEW, $transaction);

        return $this->render('transaction/show.html.twig', [
            'transaction' => $transaction,
            'comments' => $commentRepository->findBy(['transaction' => $transaction], ['createdAt' => 'ASC']),
        ]);
    }

    #[Route('/transactions/{id}/edit', name: 'transaction_edit', methods: ['GET', 'POST'])]
    public function edit(Transaction $transaction, Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted(TransactionVoter::EDIT, $transaction);

        $form = $this->createForm(TransactionType::class, $transaction);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', 'Transaction modifiee avec succes.');

            return $this->redirectToRoute('transaction_index', ['walletId' => $transaction->getWallet()->getId()]);
        }

        return $this->render('transaction/edit.html.twig', [
            'form' => $form,
            'transaction' => $transaction,
        ]);
    }

    #[Route('/transactions/{id}/delete', name: 'transaction_delete', methods: ['POST'])]
    public function delete(Transaction $transaction, Request $request, CommentRepository $commentRepository, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted(TransactionVoter::EDIT, $transaction);

        if ($this->isCsrfTokenValid('delete-transaction-'.$transaction->getId(), $request->request->get('_token'))) {
            $walletId = $transaction->getWallet()->getId();

            foreach ($commentRepository->findBy(['transaction' => $transaction]) as $comment) {
                $entityManager->remove($comment);
            }

            $entityManager->remove($transaction);
            $entityManager->flush();

            $this->addFlash('success', 'Transaction supprimee.');

            return $this->redirectToRoute('transaction_index', ['walletId' => $walletId]);
        }

        return $this->redirectToRoute('transaction_show', ['id' => $transaction->getId()]);
    }

    #[Route('/transactions/{id}/comments', name: 'transaction_comment_new', methods: ['POST'])]
    public function addComment(Transaction $transaction, Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted(TransactionVoter::VIEW, $transaction);

        $content = trim((string) $request->request->get('content'));

        if ('' !== $content) {
            $comment = new Comment();
            $comment->setContent($content);
            $comment->setTransaction($transaction);
            $comment->setAuthor($this->getCurrentUser());
            $entityManager->persist($comment);
            $entityManager->flush();
        }

        return $this->redirectToRoute('transaction_show', ['id' => $transaction->getId()]);
    }

    private function findWalletOrFail(int $walletId, WalletRepository $walletRepository): Wallet
    {
        $wallet = $walletRepository->find($walletId);

        if (!$wallet) {
            throw $this->createNotFoundException('Portefeuille introuvable.');
        }

        return $wallet;
    }

    private function denyAccessUnlessWalletAccessible(Wallet $wallet, WalletRepository $walletRepository): void
    {
        if (!$walletRepository->isAccessibleByUser($wallet, $this->getCurrentUser())) {
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
