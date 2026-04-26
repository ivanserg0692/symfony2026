<?php

namespace App\Controller\Admin;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Security\Voter\UsersVoter;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Config\KeyValueStore;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ArrayFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class UserCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly UserRepository $userRepository
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return User::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('User')
            ->setEntityLabelInPlural('Users')
            ->setDefaultSort(['id' => 'DESC'])
            ->setSearchFields(['id', 'email', 'firstName', 'secondName']);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(TextFilter::new('email'))
            ->add(TextFilter::new('firstName'))
            ->add(TextFilter::new('secondName'))
            ->add(ArrayFilter::new('roles'))
            ->add(EntityFilter::new('groups'));
    }
    public function configureActions(Actions $actions): Actions
    {
        $backToList = Action::new('backToList', 'Back to list', 'fa fa-arrow-left')
            ->linkToCrudAction(Action::INDEX);

        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->update(Crud::PAGE_INDEX, Action::DETAIL, fn (Action $action): Action =>
                $action->displayIf(fn (User $user): bool => $this->isGranted(UsersVoter::VIEW, $user))
            )
            ->update(Crud::PAGE_INDEX, Action::EDIT, fn (Action $action): Action =>
                $action->displayIf(fn (User $user): bool => $this->isGranted(UsersVoter::EDIT, $user))
            )
            ->update(Crud::PAGE_INDEX, Action::DELETE, fn (Action $action): Action =>
                $action->displayIf(fn (User $user): bool => $this->isGranted(UsersVoter::EDIT, $user))
            )
            ->update(Crud::PAGE_DETAIL, Action::EDIT, fn (Action $action): Action =>
                $action->displayIf(fn (User $user): bool => $this->isGranted(UsersVoter::EDIT, $user))
            )
            ->update(Crud::PAGE_DETAIL, Action::DELETE, fn (Action $action): Action =>
                $action->displayIf(fn (User $user): bool => $this->isGranted(UsersVoter::EDIT, $user))
            )
            ->add(Crud::PAGE_EDIT, $backToList);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')
            ->hideOnForm();

        yield EmailField::new('email');

        yield TextField::new('firstName');

        yield TextField::new('secondName');

        if (in_array($pageName, [Crud::PAGE_NEW, Crud::PAGE_EDIT], true)) {
            yield TextField::new('plainPassword', 'New password')
                ->setFormType(PasswordType::class)
                ->setRequired(Crud::PAGE_NEW === $pageName)
                ->setHelp(Crud::PAGE_EDIT === $pageName ? 'Leave empty to keep the current password.' : null);
        }

        if ($this->isGranted(UsersVoter::ADMINISTER, new User())) {
            yield ArrayField::new('roles');

            yield AssociationField::new('groups')
                ->setFormTypeOption('by_reference', false)
                ->setFormTypeOption('choice_label', 'name');
        }
    }

    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        $this->hashPlainPassword($entityInstance);

        parent::persistEntity($entityManager, $entityInstance);
    }

    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        $this->denyAccessUnlessGranted(UsersVoter::EDIT, $entityInstance);
        $this->hashPlainPassword($entityInstance);

        parent::updateEntity($entityManager, $entityInstance);
    }

    public function detail(AdminContext $context): KeyValueStore|Response
    {
        $this->denyAccessToUser($context, UsersVoter::VIEW);

        return parent::detail($context);
    }

    public function edit(AdminContext $context): KeyValueStore|Response
    {
        $this->denyAccessToUser($context, UsersVoter::EDIT);

        return parent::edit($context);
    }

    public function delete(AdminContext $context): Response
    {
        $this->denyAccessToUser($context, UsersVoter::EDIT);

        return parent::delete($context);
    }

    private function hashPlainPassword(object $entityInstance): void
    {
        if (!$entityInstance instanceof User) {
            return;
        }

        $plainPassword = $entityInstance->getPlainPassword();
        if ($plainPassword === null || $plainPassword === '') {
            return;
        }

        $entityInstance->setPassword($this->passwordHasher->hashPassword($entityInstance, $plainPassword));
        $entityInstance->setPlainPassword(null);
    }

    private function denyAccessToUser(AdminContext $context, string $attribute): void
    {
        $user = $context->getEntity()->getInstance();

        if ($user instanceof User) {
            $this->denyAccessUnlessGranted($attribute, $user);
        }
    }
}
