<?php

namespace App\Controller\Api;

use App\Entity\Expense;
use App\Entity\Income;
use App\Entity\Transaction;
use App\Entity\User;
use App\Repository\CategoryRepository;
use App\Repository\TransactionRepository;
use App\Repository\WalletRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/v1/transactions')]
#[IsGranted('ROLE_USER')]
class TransactionApiController extends AbstractController
{
    #[Route('', name: 'api_transaction_index', methods: ['GET'])]
    public function index(TransactionRepository $transactionRepository, SerializerInterface $serializer): JsonResponse
    {
        $transactions = $transactionRepository->findBy(['author' => $this->getCurrentUser()], ['date' => 'DESC']);

        return new JsonResponse(
            $serializer->serialize($transactions, 'json', ['groups' => ['transaction:read']]),
            200,
            [],
            true
        );
    }

    #[Route('/{id}', name: 'api_transaction_show', methods: ['GET'])]
    public function show(Transaction $transaction, SerializerInterface $serializer): JsonResponse
    {
        $this->denyAccessUnlessOwner($transaction);

        return new JsonResponse(
            $serializer->serialize($transaction, 'json', ['groups' => ['transaction:read']]),
            200,
            [],
            true
        );
    }

    #[Route('', name: 'api_transaction_create', methods: ['POST'])]
    public function create(
        Request $request,
        SerializerInterface $serializer,
        ValidatorInterface $validator,
        EntityManagerInterface $entityManager,
        CategoryRepository $categoryRepository,
        WalletRepository $walletRepository,
    ): JsonResponse {
        $data = json_decode($request->getContent(), true) ?? [];
        $type = $data['type'] ?? null;

        $transaction = match ($type) {
            'expense' => new Expense(),
            'income' => new Income(),
            default => null,
        };

        if (null === $transaction) {
            return new JsonResponse(['error' => 'Le champ "type" doit valoir "expense" ou "income".'], 422);
        }

        $serializer->deserialize($request->getContent(), $transaction::class, 'json', [
            'groups' => ['transaction:write'],
            AbstractNormalizer::OBJECT_TO_POPULATE => $transaction,
        ]);

        $transaction->setAuthor($this->getCurrentUser());

        if (!empty($data['categoryId']) && $category = $categoryRepository->find($data['categoryId'])) {
            $transaction->setCategory($category);
        }

        if (!empty($data['walletId']) && $wallet = $walletRepository->find($data['walletId'])) {
            $transaction->setWallet($wallet);
        }

        $errors = $validator->validate($transaction);
        if (count($errors) > 0) {
            $messages = array_map(static fn ($error) => $error->getMessage(), iterator_to_array($errors));

            return new JsonResponse(['errors' => $messages], 422);
        }

        $entityManager->persist($transaction);
        $entityManager->flush();

        return new JsonResponse(
            $serializer->serialize($transaction, 'json', ['groups' => ['transaction:read']]),
            201,
            [],
            true
        );
    }

    private function denyAccessUnlessOwner(Transaction $transaction): void
    {
        if ($transaction->getAuthor() !== $this->getCurrentUser()) {
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
