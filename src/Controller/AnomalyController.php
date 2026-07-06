<?php

namespace App\Controller;

use App\Entity\Anomaly;
use App\Entity\User;
use App\Repository\AnomalyRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/anomalies')]
#[IsGranted('ROLE_USER')]
class AnomalyController extends AbstractController
{
    #[Route('', name: 'anomaly_index', methods: ['GET'])]
    public function index(AnomalyRepository $anomalyRepository): Response
    {
        return $this->render('anomaly/index.html.twig', [
            'anomalies' => $anomalyRepository->findBy(['user' => $this->getCurrentUser()], ['detectedAt' => 'DESC']),
        ]);
    }

    #[Route('/{id}/read', name: 'anomaly_mark_read', methods: ['POST'])]
    public function markRead(Anomaly $anomaly, Request $request, EntityManagerInterface $entityManager): Response
    {
        if ($anomaly->getUser() !== $this->getCurrentUser()) {
            throw $this->createAccessDeniedException();
        }

        if ($this->isCsrfTokenValid('read-anomaly-'.$anomaly->getId(), $request->request->get('_token'))) {
            $anomaly->setIsRead(true);
            $entityManager->flush();
        }

        return $this->redirectToRoute('anomaly_index');
    }

    private function getCurrentUser(): User
    {
        /** @var User $user */
        $user = $this->getUser();

        return $user;
    }
}
