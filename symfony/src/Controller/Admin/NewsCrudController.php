<?php

namespace App\Controller\Admin;

use App\Entity\News;
use App\Entity\User;
use App\News\NewsExportStarter;
use App\Repository\NewsRepository;
use App\Repository\NewsStatusRepository;
use App\Security\Voter\NewsVoter;
use App\Security\Voter\UsersVoter;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Config\KeyValueStore;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Dto\BatchActionDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\SlugField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Translation\TranslatorInterface;

class NewsCrudController extends AbstractCrudController
{
    private const DEFAULT_HIDDEN_INDEX_COLUMNS = ['brief', 'description'];

    public function __construct(
        private readonly AdminUrlGenerator $adminUrlGenerator,
        private readonly NewsRepository $newsRepository,
        private readonly TranslatorInterface $translator,
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

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(EntityFilter::new('status')->canSelectMultiple())
            ->add(EntityFilter::new('createdBy'))
            ->add(DateTimeFilter::new('createdAt'));
    }

    public function configureActions(Actions $actions): Actions
    {
        $actions = $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->update(Crud::PAGE_INDEX, Action::DETAIL, fn (Action $action): Action =>
                $action->displayIf(fn (News $news): bool => $this->isGranted(NewsVoter::VIEW, $news))
            )
            ->update(Crud::PAGE_INDEX, Action::EDIT, fn (Action $action): Action =>
                $action->displayIf(fn (News $news): bool => $this->isGranted(NewsVoter::EDIT, $news))
            )
            ->update(Crud::PAGE_INDEX, Action::DELETE, fn (Action $action): Action =>
                $action->displayIf(fn (News $news): bool => $this->isGranted(NewsVoter::EDIT, $news))
            )
            ->update(Crud::PAGE_DETAIL, Action::EDIT, fn (Action $action): Action =>
                $action->displayIf(fn (News $news): bool => $this->isGranted(NewsVoter::EDIT, $news))
            )
            ->update(Crud::PAGE_DETAIL, Action::DELETE, fn (Action $action): Action =>
                $action->displayIf(fn (News $news): bool => $this->isGranted(NewsVoter::EDIT, $news))
            );

        if (!$this->isGranted(UsersVoter::ADMINISTER)) {
            return $actions;
        }

        $exportSelected = Action::new('exportSelectedNews', 'admin.news.action.start_export', 'fas fa-play')
            ->linkToCrudAction('startSelectedNewsExport')
            ->addCssClass('btn btn-primary');

        $exportAll = Action::new('exportAllNews', 'admin.news.action.export_all.label', 'fas fa-file-export')
            ->createAsGlobalAction()
            ->linkToCrudAction('startAllNewsExport')
            ->askConfirmation('admin.news.action.export_all.confirmation');

        return $actions
            ->addBatchAction($exportSelected)
            ->add(Crud::PAGE_INDEX, $exportAll);
    }

    #[AdminRoute]
    public function startSelectedNewsExport(
        BatchActionDto $batchActionDto,
        NewsExportStarter $newsExportStarter,
        Request $request,
        TranslatorInterface $translator,
    ): RedirectResponse {
        $this->denyAccessUnlessGranted(UsersVoter::ADMINISTER);

        $newsIds = array_map('intval', $batchActionDto->getEntityIds());

        if ([] === $newsIds) {
            $this->addFlash('warning', $translator->trans('admin.news_export.flash.select_news'));

            return $this->redirectToReferrer($request);
        }

        $this->startNewsExport($newsExportStarter, $translator, $newsIds);

        return $this->redirectToReferrer($request);
    }

    #[AdminRoute]
    public function startAllNewsExport(
        NewsExportStarter $newsExportStarter,
        Request $request,
        TranslatorInterface $translator,
    ): RedirectResponse {
        $this->denyAccessUnlessGranted(UsersVoter::ADMINISTER);

        $this->startNewsExport($newsExportStarter, $translator);

        return $this->redirectToReferrer($request);
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->setDisabled(true),
            TextField::new('name', 'admin.news.field.name'),
            SlugField::new('slug')
                ->setTargetFieldName('name')
                ->setUnlockConfirmationMessage('admin.news.field.slug.unlock_confirmation')
                ->setHelp('admin.news.field.slug.help'),
            AssociationField::new('status', 'admin.news.field.status')
                ->setFormTypeOption('choice_label', 'name')
                ->setFormTypeOption('query_builder', fn (NewsStatusRepository $repository): QueryBuilder =>
                    $repository->createAvailableForUserQueryBuilder($this->getCurrentUser())
                )
                ->renderAsNativeWidget(),
            $this->createCreatedByField($pageName),
            TextEditorField::new('brief', 'admin.news.field.brief'),
            TextEditorField::new('description', 'admin.news.field.description'),
            DateTimeField::new('createdAt', 'admin.news.field.created_at')
                ->hideWhenCreating()
                ->setFormTypeOption('disabled', true)
                ->setFormat('dd.MM.yyyy HH:mm'),
        ];
    }

    public function createIndexQueryBuilder(SearchDto $searchDto, EntityDto $entityDto, FieldCollection $fields, FilterCollection $filters): QueryBuilder
    {
        $queryBuilder = parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters);
        $currentUser = $this->getUser();

        $this->newsRepository->ensureListRelations($queryBuilder);
        $this->newsRepository->applyVisibility($queryBuilder, $currentUser instanceof User ? $currentUser : null);

        return $queryBuilder;
    }

    public function detail(AdminContext $context): KeyValueStore|Response
    {
        $this->denyAccessToViewNews($context);

        return parent::detail($context);
    }

    public function edit(AdminContext $context): KeyValueStore|Response
    {
        $this->denyAccessToViewNews($context);
        $this->denyAccessToEditNews($context);

        return parent::edit($context);
    }

    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        $this->denyAccessUnlessGranted(NewsVoter::CHANGE_STATUS, $entityInstance);

        parent::persistEntity($entityManager, $entityInstance);
    }

    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        $this->denyAccessUnlessGranted(NewsVoter::CHANGE_STATUS, $entityInstance);
        $this->denyAccessUnlessGranted(NewsVoter::EDIT, $entityInstance);

        parent::updateEntity($entityManager, $entityInstance);
    }

    public function delete(AdminContext $context): Response
    {
        $this->denyAccessToEditNews($context);

        return parent::delete($context);
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

        $userEditUrl = $this->getUserDetailUrl($news);

        if (null === $userEditUrl) {
            return $createdByField;
        }

        return $createdByField
            ->setHelp(sprintf('<a href="%s">%s</a>', $userEditUrl, $this->translator->trans('admin.news.field.created_by_see_user')))
            ->setFormTypeOption('help_html', true);
    }

    private function denyAccessToViewNews(AdminContext $context): void
    {
        $news = $context->getEntity()->getInstance();

        if ($news instanceof News) {
            $this->denyAccessUnlessGranted(NewsVoter::VIEW, $news);
        }
    }

    private function denyAccessToEditNews(AdminContext $context): void
    {
        $news = $context->getEntity()->getInstance();

        if ($news instanceof News) {
            $this->denyAccessUnlessGranted(NewsVoter::EDIT, $news);
        }
    }

    private function createCreatedByLinkField(): TextField
    {
        return TextField::new('createdBy', 'admin.news.field.created_by')
            ->formatValue(function ($value, News $news): string {
                $user = $news->getCreatedBy();
                $userEditUrl = $this->getUserDetailUrl($news);

                if (null === $user || null === $userEditUrl) {
                    return '';
                }

                return sprintf('<a href="%s">%s</a>', $userEditUrl, $user->getEmail());
            })
            ->renderAsHtml();
    }

    private function getUserDetailUrl(News $news): ?string
    {
        $user = $news->getCreatedBy();

        if (null === $user || null === $user->getId()) {
            return null;
        }

        return $this->adminUrlGenerator
            ->unsetAll()
            ->setController(UserCrudController::class)
            ->setAction(Action::DETAIL)
            ->setEntityId($user->getId())
            ->generateUrl();
    }

    private function getCurrentUser(): ?User
    {
        $user = $this->getUser();

        return $user instanceof User ? $user : null;
    }

    private function redirectToReferrer(Request $request): RedirectResponse
    {
        return $this->redirect($request->headers->get('referer') ?? $this->generateUrl('admin', [
            '_locale' => $request->getLocale(),
        ]));
    }

    /**
     * @param list<int> $newsIds
     */
    private function startNewsExport(NewsExportStarter $newsExportStarter, TranslatorInterface $translator, array $newsIds = []): void
    {
        try {
            $newsExport = $newsExportStarter->start($newsIds);
            $this->addFlash('success', $translator->trans('admin.news_export.flash.started', [
                '%id%' => $newsExport->getId(),
            ]));
        } catch (\RuntimeException $exception) {
            $this->addFlash('warning', $translator->trans($exception->getMessage()));
        }
    }
}
