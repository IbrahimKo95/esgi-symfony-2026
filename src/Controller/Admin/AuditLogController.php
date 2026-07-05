<?php

namespace App\Controller\Admin;

use App\Repository\AuditLogRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/audit-logs')]
#[IsGranted('ROLE_ADMIN')]
class AuditLogController extends AbstractController
{
    #[Route('', name: 'admin_audit_log_index', methods: ['GET'])]
    public function index(AuditLogRepository $auditLogRepository): Response
    {
        return $this->render('admin/audit_log/index.html.twig', [
            'logs' => $auditLogRepository->findRecent(),
        ]);
    }
}
