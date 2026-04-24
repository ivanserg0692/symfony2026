<?php

namespace App\Security\Voter;

use App\Entity\User;
use App\Entity\UserGroups;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\User\UserInterface;

final class UserGroupsVoter extends Voter
{
    public const string INDEX = 'USER_GROUPS_INDEX';
    public const string VIEW = 'USER_GROUPS_VIEW';
    public const string CREATE = 'USER_GROUPS_CREATE';
    public const string EDIT = 'USER_GROUPS_EDIT';
    public const string DELETE = 'USER_GROUPS_DELETE';

    protected function supports(string $attribute, mixed $subject): bool
    {
        if (!in_array($attribute, [self::INDEX, self::VIEW, self::CREATE, self::EDIT, self::DELETE], true)) {
            return false;
        }

        return null === $subject || $subject instanceof UserGroups;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();

        if (!$user instanceof UserInterface) {
            $vote?->addReason('The user must be logged in to access user groups.');
            return false;
        }

        if (!$user instanceof User) {
            return false;
        }

        if (!$user->isAdmin()) {
            $vote?->addReason('Only administrators can access user groups.');
            return false;
        }

        return true;
    }
}
