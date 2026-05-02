<?php

namespace App\Notifier\Support;

use App\Entity\Notifications;
use App\Repository\NotificationsRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Messenger\Handler\Acknowledger;
use Symfony\Component\Messenger\Handler\BatchHandlerInterface;
use Symfony\Component\Messenger\Handler\BatchHandlerTrait;

final class CreateNotificationMessageHandler implements BatchHandlerInterface
{
    use BatchHandlerTrait;

    private const BATCH_SIZE = 100;

    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly NotificationsRepository $notificationsRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(CreateNotificationMessage $message, ?Acknowledger $ack = null): mixed
    {
        if (null === $ack) {
            $this->handleSynchronously($message);

            return null;
        }

        return $this->handle($message, $ack);
    }

    private function process(array $jobs): void
    {
        $recipientIds = [];

        foreach ($jobs as [$message]) {
            \assert($message instanceof CreateNotificationMessage);

            $recipientIds[$message->recipientId] = $message->recipientId;
        }

        $recipients = [];

        foreach ($this->userRepository->findBy(['id' => array_values($recipientIds)]) as $recipient) {
            $recipients[$recipient->getId()] = $recipient;
        }

        $pendingAcks = [];

        foreach ($jobs as [$message, $ack]) {
            \assert($message instanceof CreateNotificationMessage);

            $recipient = $recipients[$message->recipientId] ?? null;

            if (null === $recipient) {
                $ack->nack(new UnrecoverableMessageHandlingException(sprintf(
                    'Notification recipient "%d" was not found.',
                    $message->recipientId,
                )));

                continue;
            }

            $notification = new Notifications()
                ->setRecipient($recipient)
                ->setMessage($message->message);

            $this->notificationsRepository->save($notification);
            $pendingAcks[] = $ack;
        }

        if ([] === $pendingAcks) {
            return;
        }

        try {
            $this->entityManager->flush();

            foreach ($pendingAcks as $ack) {
                $ack->ack();
            }
        } catch (\Throwable $exception) {
            foreach ($pendingAcks as $ack) {
                $ack->nack($exception);
            }
        }
    }

    private function getBatchSize(): int
    {
        return self::BATCH_SIZE;
    }

    private function handleSynchronously(CreateNotificationMessage $message): void
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
