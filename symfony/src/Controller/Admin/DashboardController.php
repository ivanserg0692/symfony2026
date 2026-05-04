<?php

namespace App\Controller\Admin;

use App\Security\Voter\UserGroupsVoter;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AdminDashboard(routePath: '/admin', routeName: 'admin')]
class DashboardController extends AbstractDashboardController
{
    public function index(): Response
    {
        return $this->render('admin/dashboard.html.twig');

        // Option 1. You can make your dashboard redirect to some common page of your backend
        //
        // return $this->redirectToRoute('admin_user_index');

        // Option 2. You can make your dashboard redirect to different pages depending on the user
        //
        // if ('jane' === $this->getUser()->getUsername()) {
        //     return $this->redirectToRoute('...');
        // }

        // Option 3. You can render some custom template to display a proper dashboard with widgets, etc.
        // (tip: it's easier if your template extends from @EasyAdmin/page/content.html.twig)
        //
        // return $this->render('some/path/my-dashboard.html.twig');
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('Workspace');
    }

    public function configureMenuItems(): iterable
    {
        $userMenuItems = [
            MenuItem::linkTo(UserCrudController::class, 'Users', 'fas fa-user'),
        ];

        if ($this->isGranted(UserGroupsVoter::INDEX)) {
            $userMenuItems[] = MenuItem::linkTo(UserGroupsCrudController::class, 'Groups', 'fas fa-users-cog');
        }

        yield MenuItem::linkToDashboard('Dashboard', 'fa fa-home');

        yield MenuItem::section('Content');
        yield MenuItem::linkTo(NewsCrudController::class, 'News', 'fas fa-newspaper');
        yield MenuItem::linkTo(NewsExportCrudController::class, 'News exports', 'fas fa-file-export');

        yield MenuItem::section('Access');
        yield MenuItem::subMenu('Users', 'fas fa-users')->setSubItems($userMenuItems);
    }
}
