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
        yield TextField::new('messengerBatch.status', 'Status');
        yield IntegerField::new('messengerBatch.totalJobs', 'Total');
        yield IntegerField::new('messengerBatch.processedJobs', 'Processed');
        yield IntegerField::new('messengerBatch.failedJobs', 'Failed');
        yield DateTimeField::new('messengerBatch.createdAt', 'Created at');
        yield DateTimeField::new('messengerBatch.startedAt', 'Started at');
        yield DateTimeField::new('messengerBatch.finishedAt', 'Finished at');
    }
}
