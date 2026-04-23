<?php

namespace App\Controller\Admin;

use App\Entity\News;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\KeyValueStore;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\SlugField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;

class NewsCrudController extends AbstractCrudController
{
    private const DEFAULT_HIDDEN_INDEX_COLUMNS = ['brief', 'description'];

    public function __construct(
        private readonly AdminUrlGenerator $adminUrlGenerator,
    )
    {
    }

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
            AssociationField::new('status')
                ->setFormTypeOption('choice_label', 'name'),
            $this->createCreatedByField($pageName),
            TextEditorField::new('brief'),
            TextEditorField::new('description'),
            DateTimeField::new('createdAt', 'Created at')
                ->hideWhenCreating()
                ->setFormTypeOption('disabled', true)
                ->setFormat('dd.MM.yyyy HH:mm'),
        ];
    }

    private function createCreatedByField(string $pageName): AssociationField|TextField
    {
        if (Crud::PAGE_INDEX === $pageName || Crud::PAGE_DETAIL === $pageName) {
            return $this->createCreatedByLinkField();
        }

        return $this->createCreatedByFormField($pageName);
    }

    private function createCreatedByFormField(string $pageName): AssociationField
    {
        $createdByField = AssociationField::new('createdBy')
            ->setFormTypeOption('choice_label', 'email')
            ->setDisabled(true);

        if (Crud::PAGE_EDIT !== $pageName) {
            return $createdByField;
        }

        $news = $this->getContext()?->getEntity()?->getInstance();

        if (!$news instanceof News) {
            return $createdByField;
        }

        $userEditUrl = $this->getUserEditUrl($news);

        if (null === $userEditUrl) {
            return $createdByField;
        }

        return $createdByField
            ->setHelp(sprintf('<a href="%s">See user</a>', $userEditUrl))
            ->setFormTypeOption('help_html', true);
    }

    private function createCreatedByLinkField(): TextField
    {
        return TextField::new('createdBy', 'Created by')
            ->formatValue(function ($value, News $news): string {
                $user = $news->getCreatedBy();
                $userEditUrl = $this->getUserEditUrl($news);

                if (null === $user || null === $userEditUrl) {
                    return '';
                }

                return sprintf('<a href="%s">%s</a>', $userEditUrl, $user->getEmail());
            })
            ->renderAsHtml();
    }

    private function getUserEditUrl(News $news): ?string
    {
        $user = $news->getCreatedBy();

        if (null === $user || null === $user->getId()) {
            return null;
        }

        return $this->adminUrlGenerator
            ->unsetAll()
            ->setController(UserCrudController::class)
            ->setAction(Action::EDIT)
            ->setEntityId($user->getId())
            ->generateUrl();
    }
}
