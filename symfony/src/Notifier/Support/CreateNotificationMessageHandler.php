<?php

namespace App\Notifier\Support;

use App\Entity\Notifications;
use App\Repository\NotificationsRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

#[AsMessageHandler]
final readonly class CreateNotificationMessageHandler
{
    public function __construct(
        private UserRepository $userRepository,
        private NotificationsRepository $notificationsRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(CreateNotificationMessage $message): void
    {
        $recipient = $this->userRepository->find($message->recipientId);

        if (null === $recipient) {
            throw new UnrecoverableMessageHandlingException(sprintf(
                'Notification recipient "%d" was not found.',
                $message->recipientId,
            ));
        }

        $notification = new Notifications()
            ->setRecipient($recipient)
            ->setMessage($message->message);

        $this->notificationsRepository->save($notification);
        $this->entityManager->flush();
    }
}
