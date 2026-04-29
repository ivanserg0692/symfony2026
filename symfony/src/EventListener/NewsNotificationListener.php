<?php

namespace App\EventListener;

use App\Entity\News;
use App\Entity\NewsStatus;
use App\Enum\NewsStatusCode;
use App\Notifier\NewsNotifier;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;
use Psr\Log\LoggerInterface;

#[AsEntityListener(event: Events::postPersist, method: 'postPersist', entity: News::class)]
#[AsEntityListener(event: Events::preUpdate, method: 'preUpdate', entity: News::class)]
final readonly class NewsNotificationListener
{
    public function __construct(
        private LoggerInterface $logger,
        private NewsNotifier $newsNotifier,
    ) {
    }

    public function postPersist(News $news): void
    {
        if (NewsStatusCode::ON_MODERATION !== $news->getStatus()?->getCode()) {
            return;
        }

        $this->newsNotifier->notifyOnModeration($news);

        $this->logger->debug('News was created and moved onto moderation.', [
            'news_id' => $news->getId(),
            'news_slug' => $news->getSlug(),
        ]);
    }

    public function preUpdate(News $news, PreUpdateEventArgs $event): void
    {
        if (!$event->hasChangedField('status')) {
            return;
        }

        $oldStatus = $event->getOldValue('status');
        $newStatus = $event->getNewValue('status');

        if (!$newStatus instanceof NewsStatus || NewsStatusCode::ON_MODERATION !== $newStatus->getCode()) {
            return;
        }

        if ($oldStatus instanceof NewsStatus && NewsStatusCode::ON_MODERATION === $oldStatus->getCode()) {
            return;
        }

        $this->newsNotifier->notifyOnModeration($news);

        $this->logger->debug('News status changed to on moderation.', [
            'news_id' => $news->getId(),
            'news_slug' => $news->getSlug(),
            'old_status' => $oldStatus instanceof NewsStatus ? $oldStatus->getCode()?->value : null,
            'new_status' => $newStatus->getCode()?->value,
        ]);
    }
}
