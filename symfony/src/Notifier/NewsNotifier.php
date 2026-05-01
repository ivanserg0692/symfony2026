<?php

namespace App\Notifier;

use App\Entity\News;
use App\Notification\NewsOnModerationNotification;
use App\Repository\UserRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class NewsNotifier
{
    public function __construct(
        private MailerInterface     $mailer,
        private TranslatorInterface $translator,
        private UserRepository      $userRepository,
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

        $this->sendEmailToAdmins($this->createOnModerationNotification($news), $adminEmails);
    }

    /**
     * @param list<string> $adminEmails
     * @throws TransportExceptionInterface
     */
    private function sendEmailToAdmins(NewsOnModerationNotification $notification, array $adminEmails): void
    {
        $email = new Email()
            ->from($this->mailerFrom)
            ->bcc(...$adminEmails)
            ->subject($notification->getEmailSubject())
            ->text($notification->getEmailContent());

        $this->mailer->send($email);
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
