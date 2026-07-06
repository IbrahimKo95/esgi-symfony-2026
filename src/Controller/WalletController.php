<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\Wallet;
use App\Entity\WalletMember;
use App\Form\WalletMemberInviteType;
use App\Form\WalletType;
use App\Repository\UserRepository;
use App\Repository\WalletMemberRepository;
use App\Repository\WalletRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/wallets')]
#[IsGranted('ROLE_USER')]
class WalletController extends AbstractController
{
    #[Route('', name: 'wallet_index', methods: ['GET'])]
    public function index(WalletRepository $walletRepository): Response
    {
        return $this->render('wallet/index.html.twig', [
            'wallets' => $walletRepository->findForUser($this->getCurrentUser()),
        ]);
    }

    #[Route('/new', name: 'wallet_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $wallet = new Wallet();
        $wallet->setOwner($this->getCurrentUser());

        $form = $this->createForm(WalletType::class, $wallet);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($wallet);

            // le proprietaire est automatiquement membre owner du wallet
            $member = new WalletMember();
            $member->setWallet($wallet);
            $member->setUser($this->getCurrentUser());
            $member->setRole('owner');
            $entityManager->persist($member);

            $entityManager->flush();

            $this->addFlash('success', 'Portefeuille cree avec succes.');

            return $this->redirectToRoute('wallet_show', ['id' => $wallet->getId()]);
        }

        return $this->render('wallet/new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'wallet_show', methods: ['GET'])]
    public function show(Wallet $wallet, WalletRepository $walletRepository, WalletMemberRepository $walletMemberRepository): Response
    {
        $this->denyAccessUnlessWalletAccessible($wallet, $walletRepository);

        return $this->render('wallet/show.html.twig', [
            'wallet' => $wallet,
            'members' => $walletMemberRepository->findByWallet($wallet),
            'inviteForm' => $this->createForm(WalletMemberInviteType::class)->createView(),
            'isOwner' => $wallet->getOwner() === $this->getCurrentUser(),
        ]);
    }

    #[Route('/{id}/invite', name: 'wallet_invite', methods: ['POST'])]
    public function invite(
        Wallet $wallet,
        Request $request,
        EntityManagerInterface $entityManager,
        UserRepository $userRepository,
        WalletMemberRepository $walletMemberRepository,
    ): Response {
        if ($wallet->getOwner() !== $this->getCurrentUser()) {
            throw $this->createAccessDeniedException();
        }

        if (!$wallet->isShared()) {
            $this->addFlash('error', 'Ce portefeuille n\'est pas collaboratif. Activez-le pour pouvoir inviter des membres.');

            return $this->redirectToRoute('wallet_show', ['id' => $wallet->getId()]);
        }

        $form = $this->createForm(WalletMemberInviteType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $email = $form->get('email')->getData();
            $role = $form->get('role')->getData();
            $invitedUser = $userRepository->findOneBy(['email' => $email]);

            if (!$invitedUser) {
                $this->addFlash('error', 'Aucun utilisateur trouve avec cet email.');
            } elseif ($invitedUser === $wallet->getOwner()) {
                $this->addFlash('error', 'Le proprietaire est deja membre de ce portefeuille.');
            } elseif (in_array($invitedUser, array_map(fn (WalletMember $m) => $m->getUser(), $walletMemberRepository->findByWallet($wallet)), true)) {
                $this->addFlash('error', 'Cet utilisateur est deja membre de ce portefeuille.');
            } else {
                $member = new WalletMember();
                $member->setWallet($wallet);
                $member->setUser($invitedUser);
                $member->setRole($role);
                $entityManager->persist($member);
                $entityManager->flush();

                $this->addFlash('success', 'Membre invite avec succes.');
            }
        }

        return $this->redirectToRoute('wallet_show', ['id' => $wallet->getId()]);
    }

    #[Route('/{id}/toggle-shared', name: 'wallet_toggle_shared', methods: ['POST'])]
    public function toggleShared(Wallet $wallet, Request $request, EntityManagerInterface $entityManager): Response
    {
        if ($wallet->getOwner() !== $this->getCurrentUser()) {
            throw $this->createAccessDeniedException();
        }

        if ($this->isCsrfTokenValid('toggle-shared-'.$wallet->getId(), $request->request->get('_token'))) {
            $wallet->setIsShared(!$wallet->isShared());
            $entityManager->flush();

            $this->addFlash('success', $wallet->isShared() ? 'Portefeuille rendu collaboratif.' : 'Portefeuille rendu personnel.');
        }

        return $this->redirectToRoute('wallet_show', ['id' => $wallet->getId()]);
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
