<?php

namespace App\Controller\Admin;

use App\Entity\UserGroups;
use App\Security\Voter\UserGroupsVoter;
use EasyCorp\Bundle\EasyAdminBundle\Config\KeyValueStore;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\HttpFoundation\Response;

class UserGroupsCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return UserGroups::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('User group')
            ->setEntityLabelInPlural('User groups');
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')
            ->hideOnForm();

        yield TextField::new('name');

        yield BooleanField::new('isAdmin');

        yield IntegerField::new('usersCount', 'Users')->setDisabled(true);
    }

    public function index(AdminContext $context): KeyValueStore|Response
    {
        $this->denyAccessUnlessGranted(UserGroupsVoter::INDEX);

        return parent::index($context);
    }

    public function new(AdminContext $context): KeyValueStore|Response
    {
        $this->denyAccessUnlessGranted(UserGroupsVoter::CREATE);

        return parent::new($context);
    }

    public function detail(AdminContext $context): KeyValueStore|Response
    {
        $this->denyAccessToGroup($context, UserGroupsVoter::VIEW);

        return parent::detail($context);
    }

    public function edit(AdminContext $context): KeyValueStore|Response
    {
        $this->denyAccessToGroup($context, UserGroupsVoter::EDIT);

        return parent::edit($context);
    }

    public function delete(AdminContext $context): Response
    {
        $this->denyAccessToGroup($context, UserGroupsVoter::DELETE);

        return parent::delete($context);
    }

    private function denyAccessToGroup(AdminContext $context, string $attribute): void
    {
        $group = $context->getEntity()->getInstance();

        if ($group instanceof UserGroups) {
            $this->denyAccessUnlessGranted($attribute, $group);
        }
    }
}
