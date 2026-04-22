<?php

namespace App\Controller\Admin;

use App\Entity\News;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\SlugField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class NewsCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return News::class;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->setDisabled(true),
            TextField::new('name'),
            SlugField::new('slug')
                ->setTargetFieldName('name')
                ->setUnlockConfirmationMessage('Edit slug manually?')
                ->setHelp('Slug will be generated automatically'),
            TextEditorField::new('brief'),
            TextEditorField::new('description'),
            DateTimeField::new('createdAt', 'Created at')
                ->hideWhenCreating()
                ->setFormTypeOption('disabled', true)
                ->setFormat('dd.MM.yyyy HH:mm'),
        ];
    }
}
