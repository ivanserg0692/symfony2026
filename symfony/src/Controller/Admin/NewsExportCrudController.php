<?php

namespace App\Controller\Admin;

use App\Entity\NewsExport;
use App\Entity\User;
use App\News\NewsExportStarter;
use App\Repository\NewsRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class NewsExportCrudController extends AbstractCrudController
{
    private const NEWS_PER_PAGE = 20;

    public static function getEntityFqcn(): string
    {
        return NewsExport::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setPaginatorUseOutputWalkers(true)
            ->overrideTemplate('crud/index', 'admin/news_export/index.html.twig');
    }

    public function configureActions(Actions $actions): Actions
    {
        $startExport = Action::new('startExport', 'Start export', 'fas fa-play')
            ->createAsGlobalAction()
            ->linkToCrudAction('selectNews')
            ->addCssClass('news-export-modal-trigger')
            ->setHtmlAttributes([
                'data-news-export-modal-trigger' => 'true',
            ]);

        return $actions
            ->disable(Action::NEW, Action::EDIT, Action::DELETE, Action::BATCH_DELETE)
            ->add(Crud::PAGE_INDEX, $startExport);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id');
        yield TextField::new('messengerBatch.status', 'Status');
        yield IntegerField::new('messengerBatch.totalJobs', 'Total');
        yield IntegerField::new('messengerBatch.processedJobs', 'Processed');
        yield IntegerField::new('messengerBatch.failedJobs', 'Failed');
        yield DateTimeField::new('messengerBatch.createdAt', 'Created at');
        yield DateTimeField::new('messengerBatch.startedAt', 'Started at');
        yield DateTimeField::new('messengerBatch.finishedAt', 'Finished at');
    }

    #[AdminRoute]
    public function startExport(
        NewsExportStarter $newsExportStarter,
        Request $request,
    ): RedirectResponse {
        if (!$this->isCsrfTokenValid('start_news_export', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $newsIds = array_map('intval', $request->request->all('news_ids'));

        return $this->startSelectedExport($newsExportStarter, $newsIds, $request);
    }

    #[AdminRoute]
    public function selectNews(
        NewsRepository $newsRepository,
        AdminUrlGenerator $adminUrlGenerator,
        Request $request,
    ): Response {
        $pagination = $this->findSelectableNewsPage($newsRepository, $request);
        $returnUrl = $this->getReturnUrl($request);

        return $this->render('admin/news_export/_news_selection.html.twig', [
            'startExportUrl' => $this->generateCrudActionUrl($adminUrlGenerator, 'startExport'),
            'returnUrl' => $returnUrl,
            'news' => $pagination['news'],
            'currentPage' => $pagination['currentPage'],
            'totalPages' => $pagination['totalPages'],
            'totalItems' => $pagination['totalItems'],
            'previousPageUrl' => null === $pagination['previousPage']
                ? null
                : $this->generateCrudActionUrl($adminUrlGenerator, 'selectNews', $pagination['previousPage'], $returnUrl),
            'nextPageUrl' => null === $pagination['nextPage']
                ? null
                : $this->generateCrudActionUrl($adminUrlGenerator, 'selectNews', $pagination['nextPage'], $returnUrl),
        ]);
    }

    /**
     * @param list<int> $newsIds
     */
    private function startSelectedExport(NewsExportStarter $newsExportStarter, array $newsIds, Request $request): RedirectResponse
    {
        try {
            $newsExport = $newsExportStarter->start($newsIds);
            $this->addFlash('success', sprintf('News export #%d has been started.', $newsExport->getId()));
        } catch (\RuntimeException $exception) {
            $this->addFlash('warning', $exception->getMessage());
        }

        return $this->redirect($this->getReturnUrl($request));
    }

    /**
     * @return array{
     *     news: list<News>,
     *     currentPage: int,
     *     totalPages: int,
     *     totalItems: int,
     *     previousPage: int|null,
     *     nextPage: int|null
     * }
     */
    private function findSelectableNewsPage(NewsRepository $newsRepository, Request $request): array
    {
        $currentPage = max(1, $request->query->getInt('page', 1));
        $queryBuilder = $newsRepository
            ->createQueryBuilder('news')
            ->orderBy('news.createdAt', 'DESC');

        $newsRepository->applyVisibility($queryBuilder, $this->getCurrentUser());

        $totalItems = \count(new Paginator($queryBuilder->getQuery()));
        $totalPages = max(1, (int) ceil($totalItems / self::NEWS_PER_PAGE));

        if ($currentPage > $totalPages) {
            $currentPage = $totalPages;
        }

        $query = $queryBuilder
            ->setFirstResult(($currentPage - 1) * self::NEWS_PER_PAGE)
            ->setMaxResults(self::NEWS_PER_PAGE)
            ->getQuery();

        $paginator = new Paginator($query);

        return [
            'news' => iterator_to_array($paginator->getIterator(), false),
            'currentPage' => $currentPage,
            'totalPages' => $totalPages,
            'totalItems' => $totalItems,
            'previousPage' => $currentPage > 1 ? $currentPage - 1 : null,
            'nextPage' => $currentPage < $totalPages ? $currentPage + 1 : null,
        ];
    }

    private function getCurrentUser(): ?User
    {
        $user = $this->getUser();

        return $user instanceof User ? $user : null;
    }

    private function getReturnUrl(Request $request): string
    {
        $returnUrl = $request->request->get('return_url') ?: $request->query->get('returnUrl');

        if (\is_string($returnUrl) && '' !== $returnUrl) {
            return $returnUrl;
        }

        return $request->headers->get('referer') ?? $this->generateUrl('admin');
    }

    private function generateCrudActionUrl(
        AdminUrlGenerator $adminUrlGenerator,
        string $action,
        ?int $page = null,
        ?string $returnUrl = null,
    ): string {
        $adminUrlGenerator
            ->unsetAll()
            ->setController(self::class)
            ->setAction($action);

        if (null !== $page) {
            $adminUrlGenerator->set('page', $page);
        }

        if (null !== $returnUrl) {
            $adminUrlGenerator->set('returnUrl', $returnUrl);
        }

        return $adminUrlGenerator->generateUrl();
    }
}
