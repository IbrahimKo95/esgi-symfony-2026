<?php

namespace App\Form;

use App\Entity\Budget;
use App\Entity\Category;
use App\Entity\User;
use App\Entity\Wallet;
use App\Repository\WalletRepository;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class BudgetType extends AbstractType
{
    public function __construct(
        private readonly Security $security,
        private readonly WalletRepository $walletRepository,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var User $user */
        $user = $this->security->getUser();

        $builder
            ->add('wallet', EntityType::class, [
                'label' => 'Portefeuille',
                'class' => Wallet::class,
                'choices' => $this->walletRepository->findForUser($user),
                'choice_label' => 'name',
            ])
            ->add('category', EntityType::class, [
                'label' => 'Categorie',
                'class' => Category::class,
                'query_builder' => fn (EntityRepository $repository) => $repository->createQueryBuilder('c')
                    ->where('c.isDefault = true')
                    ->orWhere('c.owner = :user')
                    ->setParameter('user', $user)
                    ->orderBy('c.name', 'ASC'),
                'choice_label' => 'name',
            ])
            ->add('month', ChoiceType::class, [
                'label' => 'Mois',
                'choices' => $this->buildMonthChoices(),
            ])
            ->add('amount', NumberType::class, [
                'label' => 'Montant plafond',
                'scale' => 2,
            ])
        ;

        $builder->get('month')->addModelTransformer(new CallbackTransformer(
            fn (?\DateTimeImmutable $month) => $month?->format('Y-m-01'),
            fn (?string $month) => $month ? new \DateTimeImmutable($month) : null,
        ));
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Budget::class,
        ]);
    }

    private const MONTH_NAMES_FR = [
        1 => 'Janvier', 2 => 'Fevrier', 3 => 'Mars', 4 => 'Avril',
        5 => 'Mai', 6 => 'Juin', 7 => 'Juillet', 8 => 'Aout',
        9 => 'Septembre', 10 => 'Octobre', 11 => 'Novembre', 12 => 'Decembre',
    ];

    /**
     * @return array<string, string>
     */
    private function buildMonthChoices(): array
    {
        $choices = [];
        $date = new \DateTimeImmutable('first day of this month');

        for ($i = -2; $i <= 3; ++$i) {
            $month = $date->modify(sprintf('%+d months', $i));
            $label = self::MONTH_NAMES_FR[(int) $month->format('n')].' '.$month->format('Y');
            $choices[$label] = $month->format('Y-m-01');
        }

        return $choices;
    }
}
