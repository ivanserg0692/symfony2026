<?php

namespace App\Notifier\Support;

use App\Entity\Notifications;
use App\Repository\NotificationsRepository;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\Notifier\Channel\ChannelInterface;
use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\Recipient\RecipientInterface;

#[AutoconfigureTag('notifier.channel', ['channel' => 'notifications'])]
final readonly class NotificationsChannel implements ChannelInterface
{
    public function __construct(
        private NotificationsRepository $notificationsRepository,
    ) {
    }

    public function notify(Notification $notification, RecipientInterface $recipient, ?string $transportName = null): void
    {
        if (!$recipient instanceof UserRecipient) {
            return;
        }

        $this->send($notification, $recipient);
    }

    public function supports(Notification $notification, RecipientInterface $recipient): bool
    {
        return $recipient instanceof UserRecipient;
    }

    private function send(Notification $notification, UserRecipient $recipient): void
    {
        $entity = new Notifications()
            ->setRecipient($recipient->getUser())
            ->setMessage($notification->getContent() ?: $notification->getSubject());

        $this->notificationsRepository->save($entity);

    }
}
