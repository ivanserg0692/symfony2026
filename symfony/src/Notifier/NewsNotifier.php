<?php

namespace App\Notifier;

use App\Entity\News;
use App\Notification\NewsOnModerationNotification;
use App\Repository\UserRepository;
use Symfony\Component\Notifier\NotifierInterface;
use Symfony\Component\Notifier\Recipient\Recipient;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class NewsNotifier
{
    public function __construct(
        private NotifierInterface $notifier,
        private TranslatorInterface $translator,
        private UserRepository $userRepository,
    ) {
    }

    public function notifyOnModeration(News $news): void
    {
        $recipients = array_map(
            static fn (string $email): Recipient => new Recipient((string) $email),
            $this->userRepository
                ->createAdminsQueryBuilder()
                ->select('users.email')
                ->getQuery()
                ->getSingleColumnResult(),
        );

        if ([] === $recipients) {
            return;
        }

        $this->notifier->send(
            $this->createOnModerationNotification($news),
            ...$recipients,
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
