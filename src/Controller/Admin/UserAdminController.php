<?php

namespace App\Controller\Admin;

use App\Entity\AuditLog;
use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/users')]
#[IsGranted('ROLE_ADMIN')]
class UserAdminController extends AbstractController
{
    private const AVAILABLE_ROLES = ['ROLE_USER', 'ROLE_ADVISOR', 'ROLE_ADMIN'];

    #[Route('', name: 'admin_user_index', methods: ['GET'])]
    public function index(UserRepository $userRepository): Response
    {
        return $this->render('admin/user/index.html.twig', [
            'users' => $userRepository->findBy([], ['id' => 'ASC']),
            'availableRoles' => self::AVAILABLE_ROLES,
        ]);
    }

    #[Route('/{id}/role', name: 'admin_user_change_role', methods: ['POST'])]
    public function changeRole(User $user, Request $request, EntityManagerInterface $entityManager): Response
    {
        $role = $request->request->get('role');

        if (!in_array($role, self::AVAILABLE_ROLES, true)) {
            $this->addFlash('error', 'Role invalide.');

            return $this->redirectToRoute('admin_user_index');
        }

        $previousRoles = $user->getRoles();
        $user->setRoles([$role]);

        $log = new AuditLog();
        $log->setActor($this->getAdmin());
        $log->setAction('user.role_changed');
        $log->setTargetType('User');
        $log->setTargetId($user->getId());
        $log->setMetadata(['from' => $previousRoles, 'to' => [$role]]);
        $entityManager->persist($log);

        $entityManager->flush();

        $this->addFlash('success', 'Role mis a jour.');

        return $this->redirectToRoute('admin_user_index');
    }

    #[Route('/{id}/toggle-active', name: 'admin_user_toggle_active', methods: ['POST'])]
    public function toggleActive(User $user, EntityManagerInterface $entityManager): Response
    {
        $user->setIsActive(!$user->isActive());

        $log = new AuditLog();
        $log->setActor($this->getAdmin());
        $log->setAction($user->isActive() ? 'user.activated' : 'user.deactivated');
        $log->setTargetType('User');
        $log->setTargetId($user->getId());
        $entityManager->persist($log);

        $entityManager->flush();

        $this->addFlash('success', 'Statut du compte mis a jour.');

        return $this->redirectToRoute('admin_user_index');
    }

    private function getAdmin(): User
    {
        /** @var User $user */
        $user = $this->getUser();

        return $user;
    }
}
