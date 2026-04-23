<?php

namespace App\Security\Voter;

use App\Entity\News;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\User\UserInterface;

final class NewsVoter extends Voter
{
    public const string VIEW = 'NEWS_VIEW';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::VIEW], true)
            && $subject instanceof News;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        if (!$subject instanceof News) {
            return false;
        }

        return match ($attribute) {
            self::VIEW => $this->canView($subject, $token->getUser()),
            default => false,
        };
    }

    private function canView(News $news, UserInterface|null $user): bool
    {
        if ($news->getStatus()?->isPublic()) {
            return true;
        }

        if (!$user instanceof UserInterface) {
            return false;
        }

        return true;
    }
}
