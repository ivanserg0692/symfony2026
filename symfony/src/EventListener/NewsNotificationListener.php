<?php

namespace App\EventListener;

use App\Entity\News;
use App\Entity\NewsStatus;
use App\Enum\NewsStatusCode;
use App\Notifier\NewsNotifier;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

#[AsEntityListener(event: Events::postPersist, method: 'postPersist', entity: News::class)]
#[AsEntityListener(event: Events::preUpdate, method: 'preUpdate', entity: News::class)]
#[AsDoctrineListener(event: Events::postFlush)]
final class NewsNotificationListener
{
    /**
     * @var array<int, News>
     */
    private array $queuedNews = [];

    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly NewsNotifier $newsNotifier,
    ) {
    }

    public function postPersist(News $news): void
    {
        if (NewsStatusCode::ON_MODERATION !== $news->getStatus()?->getCode()) {
            return;
        }

        $this->queueNews($news);

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

        $this->queueNews($news);

        $this->logger->debug('News status changed to on moderation.', [
            'news_id' => $news->getId(),
            'news_slug' => $news->getSlug(),
            'old_status' => $oldStatus instanceof NewsStatus ? $oldStatus->getCode()?->value : null,
            'new_status' => $newStatus->getCode()?->value,
        ]);
    }

    /**
     * @throws TransportExceptionInterface
     */
    public function postFlush(): void
    {
        if ([] === $this->queuedNews) {
            return;
        }

        $queuedNews = $this->queuedNews;
        $this->queuedNews = [];

        foreach ($queuedNews as $news) {
            $this->newsNotifier->notifyOnModeration($news);
        }
    }

    private function queueNews(News $news): void
    {
        $newsId = $news->getId();
        $this->queuedNews[$newsId ?? spl_object_id($news)] = $news;
    }
}
