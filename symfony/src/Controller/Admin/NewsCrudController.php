<?php

namespace App\Controller\Admin;

use App\Entity\News;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\KeyValueStore;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\SlugField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class NewsCrudController extends AbstractCrudController
{
    private const DEFAULT_HIDDEN_INDEX_COLUMNS = ['brief', 'description'];

    public static function getEntityFqcn(): string
    {
        return News::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->overrideTemplate('crud/index', 'admin/news/index.html.twig');
    }

    public function configureResponseParameters(KeyValueStore $responseParameters): KeyValueStore
    {
        if (Crud::PAGE_INDEX === $responseParameters->get('pageName')) {
            $responseParameters->set('default_hidden_columns', self::DEFAULT_HIDDEN_INDEX_COLUMNS);
        }

        return $responseParameters;
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
            AssociationField::new('createdBy')
                ->setFormTypeOption('choice_label', 'email')->setDisabled(true),
            TextEditorField::new('brief'),
            TextEditorField::new('description'),
            DateTimeField::new('createdAt', 'Created at')
                ->hideWhenCreating()
                ->setFormTypeOption('disabled', true)
                ->setFormat('dd.MM.yyyy HH:mm'),
        ];
    }
}
