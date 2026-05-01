<?php

namespace App\Notification;

use Symfony\Component\Notifier\Notification\Notification;

final class NewsOnModerationNotification extends Notification
{
    public function __construct(
        private readonly string $emailSubject,
        private readonly string $emailContent,
    ) {
        parent::__construct($emailSubject, ['notifications']);

        $this->content($emailContent);
    }

    public function getEmailSubject(): string
    {
        return $this->emailSubject;
    }

    public function getEmailContent(): string
    {
        return $this->emailContent;
    }
}
