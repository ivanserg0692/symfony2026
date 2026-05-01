<?php

namespace App\Notifier\Support;

use App\Entity\User;
use Symfony\Component\Notifier\Recipient\EmailRecipientInterface;

final readonly class UserRecipient implements EmailRecipientInterface
{
    public function __construct(
        private User $user,
    ) {
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getEmail(): string
    {
        return (string) $this->user->getEmail();
    }
}
