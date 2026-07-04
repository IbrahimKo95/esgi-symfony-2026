<?php

namespace App\Controller;

use App\Entity\AdvisorAccess;
use App\Entity\User;
use App\Form\AdvisorInviteType;
use App\Repository\AdvisorAccessRepository;
use App\Repository\AnomalyRepository;
use App\Repository\TransactionRepository;
use App\Repository\UserRepository;
use App\Security\Voter\AdvisorAccessVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class AdvisorController extends AbstractController
{
    #[Route('/advisor-access/invite', name: 'advisor_access_invite', methods: ['GET', 'POST'])]
    public function invite(Request $request, AdvisorAccessRepository $advisorAccessRepository, UserRepository $userRepository, EntityManagerInterface $entityManager): Response
    {
        $client = $this->getCurrentUser();
        $form = $this->createForm(AdvisorInviteType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $email = $form->get('email')->getData();
            $advisor = $userRepository->findOneBy(['email' => $email]);

            if (!$advisor || !in_array('ROLE_ADVISOR', $advisor->getRoles(), true)) {
                $this->addFlash('error', 'Aucun conseiller trouve avec cet email.');
            } elseif ($advisorAccessRepository->findOneBy(['client' => $client, 'advisor' => $advisor])) {
                $this->addFlash('error', 'Une invitation existe deja pour ce conseiller.');
            } else {
                $access = new AdvisorAccess();
                $access->setClient($client);
                $access->setAdvisor($advisor);
                $access->setStatus('pending');
                $entityManager->persist($access);
                $entityManager->flush();

                $this->addFlash('success', 'Invitation envoyee.');
            }

            return $this->redirectToRoute('advisor_access_invite');
        }

        return $this->render('advisor/invite.html.twig', [
            'form' => $form,
            'accesses' => $advisorAccessRepository->findForClient($client),
        ]);
    }

    #[Route('/advisor-access/{id}/revoke', name: 'advisor_access_revoke', methods: ['POST'])]
    public function revoke(AdvisorAccess $access, Request $request, EntityManagerInterface $entityManager): Response
    {
        if ($access->getClient() !== $this->getCurrentUser()) {
            throw $this->createAccessDeniedException();
        }

        if ($this->isCsrfTokenValid('revoke-access-'.$access->getId(), $request->request->get('_token'))) {
            $access->setStatus('revoked');
            $access->setRevokedAt(new \DateTimeImmutable());
            $entityManager->flush();

            $this->addFlash('success', 'Acces revoque.');
        }

        return $this->redirectToRoute('advisor_access_invite');
    }

    #[Route('/advisor', name: 'advisor_index', methods: ['GET'])]
    #[IsGranted('ROLE_ADVISOR')]
    public function index(AdvisorAccessRepository $advisorAccessRepository): Response
    {
        return $this->render('advisor/index.html.twig', [
            'accesses' => $advisorAccessRepository->findForAdvisor($this->getCurrentUser()),
        ]);
    }

    #[Route('/advisor-access/{id}/accept', name: 'advisor_access_accept', methods: ['POST'])]
    #[IsGranted('ROLE_ADVISOR')]
    public function accept(AdvisorAccess $access, Request $request, EntityManagerInterface $entityManager): Response
    {
        if ($access->getAdvisor() !== $this->getCurrentUser()) {
            throw $this->createAccessDeniedException();
        }

        if ($this->isCsrfTokenValid('accept-access-'.$access->getId(), $request->request->get('_token'))) {
            $access->setStatus('active');
            $access->setGrantedAt(new \DateTimeImmutable());
            $entityManager->flush();

            $this->addFlash('success', 'Invitation acceptee.');
        }

        return $this->redirectToRoute('advisor_index');
    }

    #[Route('/advisor/clients/{client}', name: 'advisor_client_show', methods: ['GET'])]
    #[IsGranted('ROLE_ADVISOR')]
    public function showClient(User $client, TransactionRepository $transactionRepository, AnomalyRepository $anomalyRepository): Response
    {
        $this->denyAccessUnlessGranted(AdvisorAccessVoter::VIEW_CLIENT_DATA, $client);

        return $this->render('advisor/client_show.html.twig', [
            'client' => $client,
            'transactions' => $transactionRepository->findBy(['author' => $client], ['date' => 'DESC'], 30),
            'anomalies' => $anomalyRepository->findBy(['user' => $client], ['detectedAt' => 'DESC'], 30),
        ]);
    }

    private function getCurrentUser(): User
    {
        /** @var User $user */
        $user = $this->getUser();

        return $user;
    }
}
