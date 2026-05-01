<?php

namespace App\Security\Voter;

use App\Entity\Notifications;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\User\UserInterface;

final class NotificationsVoter extends Voter
{
    public const string VIEW = 'NOTIFICATION_VIEW';
    public const string MARK_AS_READ = 'NOTIFICATION_MARK_AS_READ';
    public const string DELETE = 'NOTIFICATION_DELETE';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::VIEW, self::MARK_AS_READ, self::DELETE], true)
            && $subject instanceof Notifications;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        if (!$subject instanceof Notifications) {
            return false;
        }

        $user = $token->getUser();

        if (!$user instanceof UserInterface) {
            return false;
        }

        if (!$user instanceof User) {
            return false;
        }

        return match ($attribute) {
            self::VIEW, self::MARK_AS_READ, self::DELETE => $this->isRecipient($subject, $user),
            default => false,
        };
    }

    private function isRecipient(Notifications $notification, User $user): bool
    {
        return $notification->getRecipient()?->getId() === $user->getId();
    }
}
