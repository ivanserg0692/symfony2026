<?php

namespace App\Notification;

use Symfony\Component\Notifier\Notification\Notification;

final class NewsOnModerationNotification extends Notification
{
    public function __construct(string $subject, string $content)
    {
        parent::__construct($subject, ['email']);

        $this->content($content);
    }
}
