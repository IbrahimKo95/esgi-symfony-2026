<?php

namespace App\Form;

use App\Entity\Category;
use App\Entity\Expense;
use App\Entity\Income;
use App\Entity\RecurringTransaction;
use App\Entity\Tag;
use App\Entity\Transaction;
use App\Entity\User;
use App\Entity\Wallet;
use App\Repository\WalletRepository;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TransactionType extends AbstractType
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
        $wallets = $this->walletRepository->findForUser($user);

        $builder
            ->add('amount', NumberType::class, [
                'label' => 'Montant',
                'scale' => 2,
                'help' => 'Le montant est exprime dans la devise du portefeuille selectionne.',
            ])
            ->add('date', DateTimeType::class, [
                'label' => 'Date',
                'widget' => 'single_text',
            ])
            ->add('description', TextType::class, [
                'label' => 'Description',
                'required' => false,
            ])
            ->add('wallet', EntityType::class, [
                'label' => 'Portefeuille',
                'class' => Wallet::class,
                'choices' => $wallets,
                'choice_label' => 'name',
            ])
            ->add('category', EntityType::class, [
                'label' => 'Categorie',
                'class' => Category::class,
                'query_builder' => function (EntityRepository $repository) use ($user) {
                    return $repository->createQueryBuilder('c')
                        ->where('c.isDefault = true')
                        ->orWhere('c.owner = :user')
                        ->setParameter('user', $user)
                        ->orderBy('c.name', 'ASC');
                },
                'choice_label' => 'name',
            ])
            ->add('tags', EntityType::class, [
                'label' => 'Tags',
                'class' => Tag::class,
                'query_builder' => fn (EntityRepository $repository) => $repository->createQueryBuilder('t')
                    ->where('t.owner = :user')
                    ->setParameter('user', $user)
                    ->orderBy('t.name', 'ASC'),
                'choice_label' => 'name',
                'multiple' => true,
                'required' => false,
            ])
        ;

        // PRE_SET_DATA : adapte les champs selon le type de transaction (Expense vs Income)
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) use ($user): void {
            $transaction = $event->getData();
            $form = $event->getForm();

            if ($transaction instanceof Income) {
                $form->add('source', TextType::class, [
                    'label' => 'Source du revenu',
                    'required' => false,
                ]);
            } elseif ($transaction instanceof Expense) {
                $form->add('isRecurring', CheckboxType::class, [
                    'label' => 'Depense recurrente',
                    'required' => false,
                ]);
                $form->add('recurringTransaction', EntityType::class, [
                    'label' => 'Programmation liee',
                    'class' => RecurringTransaction::class,
                    'query_builder' => fn (EntityRepository $repository) => $repository->createQueryBuilder('rt')
                        ->where('rt.author = :user')
                        ->setParameter('user', $user),
                    'choice_label' => 'description',
                    'required' => false,
                ]);
            }
        });

        // PRE_SUBMIT : adapte l'aide du champ montant selon le portefeuille choisi (devise associee)
        $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event): void {
            $data = $event->getData();
            $form = $event->getForm();
            $walletId = is_array($data) ? ($data['wallet'] ?? null) : null;

            $wallet = $walletId ? $this->walletRepository->find($walletId) : null;

            $form->add('amount', NumberType::class, [
                'label' => 'Montant',
                'scale' => 2,
                'help' => $wallet
                    ? sprintf('Montant exprime en %s (devise du portefeuille "%s").', $wallet->getCurrency(), $wallet->getName())
                    : 'Selectionnez un portefeuille pour voir la devise du montant.',
            ]);
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Transaction::class,
        ]);
    }
}
