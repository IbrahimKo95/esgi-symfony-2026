<?php

namespace App\Controller\Admin;

use App\Entity\Anomaly;
use App\Entity\Transaction;
use App\Entity\User;
use App\Repository\AnomalyRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/anomalies')]
#[IsGranted('ROLE_ADMIN')]
class AnomalyAdminController extends AbstractController
{
    #[Route('', name: 'admin_anomaly_index', methods: ['GET'])]
    public function index(AnomalyRepository $anomalyRepository, EntityManagerInterface $entityManager): Response
    {
        $userCount = (int) $entityManager->createQuery('SELECT COUNT(u.id) FROM '.User::class.' u WHERE u.isActive = true')->getSingleScalarResult();
        $transactionCount = (int) $entityManager->createQuery('SELECT COUNT(t.id) FROM '.Transaction::class.' t')->getSingleScalarResult();
        $anomalyCount = (int) $entityManager->createQuery('SELECT COUNT(a.id) FROM '.Anomaly::class.' a')->getSingleScalarResult();

        return $this->render('admin/anomaly/index.html.twig', [
            'anomalies' => $anomalyRepository->findBy([], ['detectedAt' => 'DESC'], 50),
            'stats' => [
                'activeUsers' => $userCount,
                'transactionCount' => $transactionCount,
                'anomalyCount' => $anomalyCount,
                'anomalyRate' => $transactionCount > 0 ? round($anomalyCount / $transactionCount * 100, 1) : 0,
            ],
        ]);
    }
}
