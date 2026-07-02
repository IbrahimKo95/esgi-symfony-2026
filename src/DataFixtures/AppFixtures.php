<?php

namespace App\DataFixtures;

use App\Entity\AdvisorAccess;
use App\Entity\Budget;
use App\Entity\Category;
use App\Entity\Expense;
use App\Entity\Income;
use App\Entity\Tag;
use App\Entity\User;
use App\Entity\Wallet;
use App\Entity\WalletMember;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    private const EXPENSE_CATEGORIES = [
        'Alimentation', 'Transport', 'Loisirs', 'Sante', 'Logement',
        'Abonnements', 'Shopping', 'Restaurant', 'Voyages', 'Education',
        'Assurance', 'Epargne', 'Autres',
    ];

    private const INCOME_CATEGORIES = ['Salaire', 'Freelance'];

    public function __construct(private readonly UserPasswordHasherInterface $passwordHasher)
    {
    }

    public function load(ObjectManager $manager): void
    {
        $faker = Factory::create('fr_FR');

        $user = $this->createUser($manager, 'user@example.com', 'password', 'ROLE_USER', 'Alice', 'Martin');
        $advisor = $this->createUser($manager, 'advisor@example.com', 'password', 'ROLE_ADVISOR', 'Bruno', 'Conseil');
        $this->createUser($manager, 'admin@example.com', 'password', 'ROLE_ADMIN', 'Claire', 'Admin');
        $contributor = $this->createUser($manager, $faker->unique()->safeEmail(), 'password', 'ROLE_USER', $faker->firstName(), $faker->lastName());

        $categories = [];
        foreach ([...self::EXPENSE_CATEGORIES, ...self::INCOME_CATEGORIES] as $name) {
            $categories[$name] = $this->createCategory($manager, $name);
        }

        $checking = $this->createWallet($manager, $user, 'Compte courant', 'checking');
        $savings = $this->createWallet($manager, $user, 'Livret A', 'savings');
        $shared = $this->createWallet($manager, $user, 'Wallet colocation', 'checking', isShared: true);
        $wallets = [$checking, $savings, $shared];

        $this->createWalletMember($manager, $shared, $user, 'owner');
        $this->createWalletMember($manager, $shared, $contributor, 'contributor');
        $this->createWalletMember($manager, $shared, $advisor, 'viewer');

        $tags = [];
        foreach (['Perso', 'Pro', 'Vacances', 'Urgent', 'Recurrent'] as $tagName) {
            $tags[] = $this->createTag($manager, $user, $tagName);
        }

        for ($i = 0; $i < 20; $i++) {
            $isExpense = $faker->boolean(70);
            $categoryName = $faker->randomElement($isExpense ? self::EXPENSE_CATEGORIES : self::INCOME_CATEGORIES);
            $date = \DateTimeImmutable::createFromMutable($faker->dateTimeBetween('-3 months', 'now'));
            $wallet = $faker->randomElement($wallets);
            $author = $wallet === $shared ? $faker->randomElement([$user, $contributor]) : $user;

            if ($isExpense) {
                $transaction = new Expense();
                $transaction->setAmount(number_format($faker->randomFloat(2, 5, 300), 2, '.', ''));
            } else {
                $transaction = new Income();
                $transaction->setSource($categoryName === 'Salaire' ? 'Salaire' : 'Freelance');
                $transaction->setAmount(number_format($faker->randomFloat(2, 300, 2500), 2, '.', ''));
            }

            $transaction->setDate($date);
            $transaction->setDescription($faker->sentence(3));
            $transaction->setCategory($categories[$categoryName]);
            $transaction->setWallet($wallet);
            $transaction->setAuthor($author);

            foreach ($faker->randomElements($tags, $faker->numberBetween(0, 2)) as $tag) {
                $transaction->addTag($tag);
            }

            $manager->persist($transaction);
        }

        $currentMonth = new \DateTimeImmutable('first day of this month');
        $budgetSpecs = [
            [$checking, $categories['Alimentation'], $currentMonth, '400.00'],
            [$checking, $categories['Transport'], $currentMonth, '150.00'],
            [$shared, $categories['Logement'], $currentMonth, '800.00'],
        ];
        foreach ($budgetSpecs as [$wallet, $category, $month, $amount]) {
            $budget = new Budget();
            $budget->setWallet($wallet);
            $budget->setCategory($category);
            $budget->setMonth($month);
            $budget->setAmount($amount);
            $budget->setOwner($user);
            $manager->persist($budget);
        }

        $advisorAccess = new AdvisorAccess();
        $advisorAccess->setClient($user);
        $advisorAccess->setAdvisor($advisor);
        $advisorAccess->setStatus('active');
        $advisorAccess->setGrantedAt(new \DateTimeImmutable());
        $manager->persist($advisorAccess);

        $manager->flush();
    }

    private function createUser(ObjectManager $manager, string $email, string $plainPassword, string $role, string $firstName, string $lastName): User
    {
        $user = new User();
        $user->setEmail($email);
        $user->setFirstName($firstName);
        $user->setLastName($lastName);
        $user->setRoles([$role]);
        $user->setPassword($this->passwordHasher->hashPassword($user, $plainPassword));
        $manager->persist($user);

        return $user;
    }

    private function createCategory(ObjectManager $manager, string $name): Category
    {
        $category = new Category();
        $category->setName($name);
        $category->setIsDefault(true);
        $manager->persist($category);

        return $category;
    }

    private function createWallet(ObjectManager $manager, User $owner, string $name, string $type, bool $isShared = false): Wallet
    {
        $wallet = new Wallet();
        $wallet->setName($name);
        $wallet->setType($type);
        $wallet->setCurrency($owner->getCurrency());
        $wallet->setOwner($owner);
        $wallet->setIsShared($isShared);
        $manager->persist($wallet);

        return $wallet;
    }

    private function createWalletMember(ObjectManager $manager, Wallet $wallet, User $user, string $role): WalletMember
    {
        $member = new WalletMember();
        $member->setWallet($wallet);
        $member->setUser($user);
        $member->setRole($role);
        $manager->persist($member);

        return $member;
    }

    private function createTag(ObjectManager $manager, User $owner, string $name): Tag
    {
        $tag = new Tag();
        $tag->setName($name);
        $tag->setOwner($owner);
        $manager->persist($tag);

        return $tag;
    }
}
