<?php

namespace App\Form;

use App\Entity\Category;
use App\Entity\RecurringTransaction;
use App\Entity\User;
use App\Entity\Wallet;
use App\Repository\WalletRepository;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class RecurringTransactionType extends AbstractType
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
            ->add('amount', NumberType::class, ['label' => 'Montant', 'scale' => 2])
            ->add('description', TextType::class, ['label' => 'Description', 'required' => false])
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
            ->add('frequency', ChoiceType::class, [
                'label' => 'Frequence',
                'choices' => [
                    'Hebdomadaire' => 'weekly',
                    'Mensuelle' => 'monthly',
                    'Annuelle' => 'yearly',
                ],
            ])
            ->add('nextOccurrence', DateType::class, [
                'label' => 'Prochaine occurrence',
                'widget' => 'single_text',
            ])
            ->add('isActive', CheckboxType::class, [
                'label' => 'Active',
                'required' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => RecurringTransaction::class,
        ]);
    }
}
