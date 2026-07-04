<?php

namespace App\Tests\Functional;

use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class AdvisorAccessControlTest extends WebTestCase
{
    public function testAdminPageRedirectsAnonymousUser(): void
    {
        $client = static::createClient();
        $client->request('GET', '/admin/users');

        self::assertResponseRedirects();
    }

    public function testAdminPageIsForbiddenForRegularUser(): void
    {
        $client = static::createClient();
        $client->loginUser($this->getUser('user@example.com'));

        $client->request('GET', '/admin/users');

        self::assertResponseStatusCodeSame(403);
    }

    public function testAdminPageIsAccessibleForAdmin(): void
    {
        $client = static::createClient();
        $client->loginUser($this->getUser('admin@example.com'));

        $client->request('GET', '/admin/users');

        self::assertResponseIsSuccessful();
    }

    public function testAdvisorCannotViewClientWithoutActiveAccess(): void
    {
        $client = static::createClient();
        $advisor = $this->getUser('advisor@example.com');
        $client->loginUser($advisor);

        // desousa.paulette@example.org (fixture "contributor") n'a jamais accorde d'acces a ce conseiller
        $otherUser = $this->getUser('user@example.com');
        $unrelatedClient = static::getContainer()->get(UserRepository::class)
            ->createQueryBuilder('u')
            ->where('u.email != :advisorEmail AND u.email != :userEmail')
            ->setParameter('advisorEmail', 'advisor@example.com')
            ->setParameter('userEmail', 'user@example.com')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        self::assertNotNull($unrelatedClient, 'Fixture attendue : un 4e utilisateur sans lien avec le conseiller.');

        $client->request('GET', '/advisor/clients/'.$unrelatedClient->getId());

        self::assertResponseStatusCodeSame(403);
    }

    public function testAdvisorCanViewClientWithActiveAccess(): void
    {
        $client = static::createClient();
        $client->loginUser($this->getUser('advisor@example.com'));

        $activeClient = $this->getUser('user@example.com');

        $client->request('GET', '/advisor/clients/'.$activeClient->getId());

        self::assertResponseIsSuccessful();
    }

    private function getUser(string $email): User
    {
        $user = static::getContainer()->get(UserRepository::class)->findOneBy(['email' => $email]);
        self::assertNotNull($user, sprintf('Utilisateur de fixture "%s" introuvable.', $email));

        return $user;
    }
}
