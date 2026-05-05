<?php

namespace App\Controller\Admin;

use App\Entity\NewsExport;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class NewsExportCrudController extends AbstractCrudController
{
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
        return $actions
            ->disable(Action::NEW, Action::EDIT, Action::DELETE, Action::BATCH_DELETE);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id');
        yield TextField::new('messengerBatch.status', 'admin.news_export.field.status');
        yield IntegerField::new('messengerBatch.totalJobs', 'admin.news_export.field.total_jobs');
        yield IntegerField::new('messengerBatch.processedJobs', 'admin.news_export.field.processed_jobs');
        yield IntegerField::new('messengerBatch.failedJobs', 'admin.news_export.field.failed_jobs');
        yield DateTimeField::new('messengerBatch.createdAt', 'admin.news_export.field.created_at');
        yield DateTimeField::new('messengerBatch.startedAt', 'admin.news_export.field.started_at');
        yield DateTimeField::new('messengerBatch.finishedAt', 'admin.news_export.field.finished_at');
    }
}
