<?php

namespace App\Notifier\Support;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Notifier\Channel\ChannelInterface;
use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\Recipient\RecipientInterface;

#[AutoconfigureTag('notifier.channel', ['channel' => 'notifications'])]
final readonly class NotificationsChannel implements ChannelInterface
{
    public function __construct(
        private MessageBusInterface $messageBus,
    ) {
    }

    /**
     * @throws ExceptionInterface
     */
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

    /**
     * @throws ExceptionInterface
     */
    private function send(Notification $notification, UserRecipient $recipient): void
    {
        $recipientId = $recipient->getUser()->getId();

        if (null === $recipientId) {
            return;
        }

        $this->messageBus->dispatch(new CreateNotificationMessage(
            $recipientId,
            $notification->getContent() ?: $notification->getSubject(),
        ));
    }
}
