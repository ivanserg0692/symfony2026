<?php

namespace App\Controller\Admin;

use App\Entity\NewsExport;
use App\Security\Voter\UsersVoter;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use League\Flysystem\FilesystemException;
use League\Flysystem\FilesystemOperator;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[IsGranted(UsersVoter::ADMINISTER)]
class NewsExportCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly FilesystemOperator $newsExportStorage,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return NewsExport::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setPaginatorUseOutputWalkers(true);
    }

    public function configureActions(Actions $actions): Actions
    {
        $download = Action::new('download', 'admin.news_export.action.download', 'fas fa-download')
            ->linkToCrudAction('download')
            ->displayIf(fn (NewsExport $newsExport): bool => null !== $newsExport->getFilePath());

        return $actions
            ->add(Crud::PAGE_INDEX, $download)
            ->add(Crud::PAGE_DETAIL, $download)
            ->disable(Action::NEW, Action::EDIT, Action::DELETE, Action::BATCH_DELETE);
    }

    #[AdminRoute]
    public function download(
        AdminContext $context,
        Request $request,
        TranslatorInterface $translator,
    ): Response {
        $newsExport = $context->getEntity()->getInstance();

        if (!$newsExport instanceof NewsExport) {
            throw $this->createNotFoundException();
        }

        $filePath = $newsExport->getFilePath();

        if (null === $filePath) {
            $this->addFlash('warning', $translator->trans('admin.news_export.flash.file_not_ready'));

            return $this->redirectToReferrer($request);
        }

        try {
            $stream = $this->newsExportStorage->readStream($filePath);
        } catch (FilesystemException) {
            $this->addFlash('danger', $translator->trans('admin.news_export.flash.file_not_found'));

            return $this->redirectToReferrer($request);
        }

        if (!\is_resource($stream)) {
            $this->addFlash('danger', $translator->trans('admin.news_export.flash.file_not_found'));

            return $this->redirectToReferrer($request);
        }

        $filename = sprintf('news-export-%d.csv', $newsExport->getId());

        return new StreamedResponse(
            static function () use ($stream): void {
                fpassthru($stream);
                fclose($stream);
            },
            Response::HTTP_OK,
            [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => sprintf('attachment; filename="%s"', $filename),
            ],
        );
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id');
        yield TextField::new('messengerBatch.status', 'admin.news_export.field.status');
        yield IntegerField::new('messengerBatch.totalJobs', 'admin.news_export.field.total_jobs');
        yield IntegerField::new('messengerBatch.processedJobs', 'admin.news_export.field.processed_jobs');
        yield IntegerField::new('messengerBatch.failedJobs', 'admin.news_export.field.failed_jobs');
        yield TextField::new('filePath', 'admin.news_export.field.file_path');
        yield DateTimeField::new('messengerBatch.createdAt', 'admin.news_export.field.created_at');
        yield DateTimeField::new('messengerBatch.startedAt', 'admin.news_export.field.started_at');
        yield DateTimeField::new('messengerBatch.finishedAt', 'admin.news_export.field.finished_at');
    }

    private function redirectToReferrer(Request $request): RedirectResponse
    {
        return $this->redirect($request->headers->get('referer') ?? $this->generateUrl('admin', [
            '_locale' => $request->getLocale(),
        ]));
    }
}
