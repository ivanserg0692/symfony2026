<?php

namespace App\Controller\Admin;

use App\Security\Voter\UserGroupsVoter;
use App\Security\Voter\UsersVoter;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AdminDashboard(routePath: '/admin/{_locale<en|ru>}', routeName: 'admin')]
class DashboardController extends AbstractDashboardController
{
    #[Route('/admin', name: 'admin_default_locale')]
    public function defaultLocale(): RedirectResponse
    {
        return $this->redirectToRoute('admin', ['_locale' => 'en']);
    }

    #[Route(
        '/admin/{path}',
        name: 'admin_default_locale_path',
        requirements: ['path' => '(?!en(?:/|$)|ru(?:/|$)).+'],
    )]
    public function defaultLocalePath(string $path, Request $request): RedirectResponse
    {
        $queryString = $request->getQueryString();
        $targetUrl = $request->getBaseUrl().'/admin/en/'.$path;

        if (null !== $queryString) {
            $targetUrl .= '?'.$queryString;
        }

        return $this->redirect($targetUrl);
    }

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
            ->setTitle('admin.dashboard.title')
            ->setLocales(['en', 'ru']);
    }

    public function configureMenuItems(): iterable
    {
        $newsMenuItems = [
            MenuItem::linkTo(NewsCrudController::class, 'admin.menu.news', 'fas fa-list'),
        ];

        if ($this->isGranted(UsersVoter::ADMINISTER)) {
            $newsMenuItems[] = MenuItem::linkTo(NewsExportCrudController::class, 'admin.menu.news_exports', 'fas fa-file-export');
        }

        $userMenuItems = [
            MenuItem::linkTo(UserCrudController::class, 'admin.menu.users', 'fas fa-user'),
        ];

        if ($this->isGranted(UserGroupsVoter::INDEX)) {
            $userMenuItems[] = MenuItem::linkTo(UserGroupsCrudController::class, 'admin.menu.groups', 'fas fa-users-cog');
        }

        yield MenuItem::linkToDashboard('admin.menu.dashboard', 'fa fa-home');

        yield MenuItem::section('admin.menu.content');
        yield MenuItem::subMenu('admin.menu.news_group', 'fas fa-newspaper')->setSubItems($newsMenuItems);

        yield MenuItem::section('admin.menu.access');
        yield MenuItem::subMenu('admin.menu.users', 'fas fa-users')->setSubItems($userMenuItems);
    }
}
