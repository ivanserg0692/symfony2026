<?php

namespace App\Notifier;

use App\Controller\Admin\NewsCrudController;
use App\Entity\News;
use App\Notification\NewsOnModerationNotification;
use App\Repository\UserRepository;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\EA;
use Symfony\Bridge\Twig\Mime\NotificationEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class NewsNotifier
{
    public function __construct(
        private MailerInterface     $mailer,
        private TranslatorInterface $translator,
        private UserRepository      $userRepository,
        private UrlGeneratorInterface $urlGenerator,
        #[Autowire('%env(string:MAILER_FROM)%')]
        private string              $mailerFrom,
    )
    {
    }

    /**
     * @throws TransportExceptionInterface
     */
    public function notifyOnModeration(News $news): void
    {
        $adminEmails = $this->userRepository
            ->createAdminsQueryBuilder()
            ->select('users.email')
            ->getQuery()
            ->getSingleColumnResult();

        if ([] === $adminEmails) {
            return;
        }

        $this->sendEmailToAdmins($this->createOnModerationNotification($news), $adminEmails, $news);
    }

    /**
     * @param list<string> $adminEmails
     * @throws TransportExceptionInterface
     */
    private function sendEmailToAdmins(NewsOnModerationNotification $notification, array $adminEmails, News $news): void
    {
        $email = new NotificationEmail()
            ->from($this->mailerFrom)
            ->to(...$adminEmails)
            ->subject($notification->getEmailSubject())
            ->content($notification->getEmailContent());

        $adminNewsUrl = $this->createAdminNewsUrl($news);

        if (null !== $adminNewsUrl) {
            $email->action(
                $this->translator->trans('news.on_moderation.action', [], 'notifications'),
                $adminNewsUrl,
            );
        }

        $this->mailer->send($email);
    }

    private function createAdminNewsUrl(News $news): ?string
    {
        if (null === $news->getId()) {
            return null;
        }

        return $this->urlGenerator->generate(
            'admin',
            [
                EA::CRUD_CONTROLLER_FQCN => NewsCrudController::class,
                EA::CRUD_ACTION => Action::EDIT,
                EA::ENTITY_ID => $news->getId(),
            ],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );
    }

    private function createOnModerationNotification(News $news): NewsOnModerationNotification
    {
        return new NewsOnModerationNotification(
            $this->translator->trans(
                'news.on_moderation.subject',
                ['%name%' => $news->getName()],
                'notifications',
            ),
            $this->translator->trans(
                'news.on_moderation.content',
                [
                    '%name%' => $news->getName(),
                    '%slug%' => $news->getSlug(),
                ],
                'notifications',
            ),
        );
    }
}
