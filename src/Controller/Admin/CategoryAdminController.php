<?php

namespace App\Controller\Admin;

use App\Entity\AuditLog;
use App\Entity\Category;
use App\Entity\User;
use App\Form\Admin\CategoryAdminType;
use App\Repository\CategoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/categories')]
#[IsGranted('ROLE_ADMIN')]
class CategoryAdminController extends AbstractController
{
    #[Route('', name: 'admin_category_index', methods: ['GET'])]
    public function index(CategoryRepository $categoryRepository): Response
    {
        return $this->render('admin/category/index.html.twig', [
            'categories' => $categoryRepository->findBy(['isDefault' => true], ['name' => 'ASC']),
        ]);
    }

    #[Route('/new', name: 'admin_category_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $category = new Category();
        $category->setIsDefault(true);

        $form = $this->createForm(CategoryAdminType::class, $category);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($category);
            $entityManager->flush();

            $this->addFlash('success', 'Categorie creee.');

            return $this->redirectToRoute('admin_category_index');
        }

        return $this->render('admin/category/new.html.twig', ['form' => $form]);
    }

    #[Route('/{id}/delete', name: 'admin_category_delete', methods: ['POST'])]
    public function delete(Category $category, Request $request, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete-category-'.$category->getId(), $request->request->get('_token'))) {
            $log = new AuditLog();
            $log->setActor($this->getAdmin());
            $log->setAction('category.deleted');
            $log->setTargetType('Category');
            $log->setTargetId($category->getId());
            $log->setMetadata(['name' => $category->getName()]);
            $entityManager->persist($log);

            $entityManager->remove($category);
            $entityManager->flush();

            $this->addFlash('success', 'Categorie supprimee.');
        }

        return $this->redirectToRoute('admin_category_index');
    }

    private function getAdmin(): User
    {
        /** @var User $user */
        $user = $this->getUser();

        return $user;
    }
}
