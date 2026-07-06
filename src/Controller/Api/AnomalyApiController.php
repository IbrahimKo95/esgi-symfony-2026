<?php

namespace App\Controller\Api;

use App\Entity\User;
use App\Repository\AnomalyRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/api/v1/anomalies')]
#[IsGranted('ROLE_USER')]
class AnomalyApiController extends AbstractController
{
    #[Route('', name: 'api_anomaly_index', methods: ['GET'])]
    public function index(AnomalyRepository $anomalyRepository, SerializerInterface $serializer): JsonResponse
    {
        $anomalies = $anomalyRepository->findBy(['user' => $this->getCurrentUser()], ['detectedAt' => 'DESC']);

        return new JsonResponse(
            $serializer->serialize($anomalies, 'json', ['groups' => ['anomaly:read']]),
            200,
            [],
            true
        );
    }

    private function getCurrentUser(): User
    {
        /** @var User $user */
        $user = $this->getUser();

        return $user;
    }
}
