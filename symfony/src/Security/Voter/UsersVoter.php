<?php

namespace App\Security\Voter;

use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\User\UserInterface;

final class UsersVoter extends Voter
{
    public const string EDIT = 'USER_EDIT';
    public const string VIEW = 'USER_VIEW';
    public const string ADMINISTER = 'USER_ADMINISTER';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::EDIT, self::VIEW, self::ADMINISTER], true)
            && $subject instanceof User;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();

        if (!$subject instanceof User) {
            return false;
        }

        if (!$user instanceof UserInterface) {
            $vote?->addReason('The user must be logged in to access this resource.');
            return false;
        }

        if (!$user instanceof User) {
            return false;
        }

        return match ($attribute) {
            self::VIEW => $this->canView($subject, $user),
            self::EDIT => $this->canEdit($subject, $user),
            self::ADMINISTER => $this->canAdminister($user),
            default => false,
        };
    }

    private function canView(User $subject, User $user): bool
    {
        return true;
    }

    private function canEdit(User $subject, User $user): bool
    {
        return $user->isAdmin() || $subject->getId() === $user->getId();
    }

    private function canAdminister(User $user): bool
    {
        return $user->isAdmin() ;
    }
}
