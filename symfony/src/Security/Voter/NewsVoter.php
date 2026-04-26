<?php

namespace App\Security\Voter;

use App\Entity\News;
use App\Entity\User;
use App\Enum\NewsStatusCode;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\User\UserInterface;

final class NewsVoter extends Voter
{
    public const string VIEW = 'NEWS_VIEW';
    public const string CHANGE_STATUS = 'CHANGE_NEWS_STATUS';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::VIEW, self::CHANGE_STATUS], true)
            && $subject instanceof News;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        if (!$subject instanceof News) {
            return false;
        }

        return match ($attribute) {
            self::VIEW => $this->canView($subject, $token->getUser()),
            self::CHANGE_STATUS => $this->canChangeStatus($subject, $token->getUser(), $vote),
            default => false,
        };
    }

    private function canView(News $news, UserInterface|null $user): bool
    {
        if ($news->getStatus()?->isPublic()) {
            return true;
        }

        if (!$user instanceof User) {
            return false;
        }

        if($user->isAdmin() || $news->getCreatedBy()?->getId() === $user->getId()) {
            return true;
        }


        if ($news->getStatus()?->isInternal()) {
            return true;
        }

        return false;
    }

    private function canChangeStatus(News $news, UserInterface|null $user, ?Vote $vote = null): bool
    {
        if (!$user instanceof User) {
            $vote?->addReason('The user must be logged in to change news status.');
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        if (in_array($news->getStatus()?->getCode(), [
            NewsStatusCode::DRAFTED,
            NewsStatusCode::ON_MODERATION,
        ], true)) {
            return true;
        }

        $vote?->addReason('The selected news status is not available for this user.');

        return false;
    }
}
