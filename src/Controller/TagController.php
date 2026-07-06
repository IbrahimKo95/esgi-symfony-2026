<?php

namespace App\Controller;

use App\Entity\Tag;
use App\Entity\User;
use App\Form\TagType;
use App\Repository\TagRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/tags')]
#[IsGranted('ROLE_USER')]
class TagController extends AbstractController
{
    #[Route('', name: 'tag_index', methods: ['GET'])]
    public function index(TagRepository $tagRepository): Response
    {
        return $this->render('tag/index.html.twig', [
            'tags' => $tagRepository->findBy(['owner' => $this->getCurrentUser()], ['name' => 'ASC']),
        ]);
    }

    #[Route('/new', name: 'tag_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $tag = new Tag();
        $tag->setOwner($this->getCurrentUser());

        $form = $this->createForm(TagType::class, $tag);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($tag);
            $entityManager->flush();

            $this->addFlash('success', 'Tag cree avec succes.');

            return $this->redirectToRoute('tag_index');
        }

        return $this->render('tag/new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/{id}/delete', name: 'tag_delete', methods: ['POST'])]
    public function delete(Tag $tag, Request $request, EntityManagerInterface $entityManager): Response
    {
        if ($tag->getOwner() !== $this->getCurrentUser()) {
            throw $this->createAccessDeniedException();
        }

        if ($this->isCsrfTokenValid('delete-tag-'.$tag->getId(), $request->request->get('_token'))) {
            $entityManager->remove($tag);
            $entityManager->flush();

            $this->addFlash('success', 'Tag supprime.');
        }

        return $this->redirectToRoute('tag_index');
    }

    private function getCurrentUser(): User
    {
        /** @var User $user */
        $user = $this->getUser();

        return $user;
    }
}
