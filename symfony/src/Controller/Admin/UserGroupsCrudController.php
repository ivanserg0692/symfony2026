<?php

namespace App\Controller\Admin;

use App\Entity\UserGroups;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

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
}
