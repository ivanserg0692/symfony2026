<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\Request;

class CurrentUserProvider
{
    private const HEADER_NAME = "X-User-Id";

    public function getRequiredUserId(Request $request): int
    {
        $value = $request->headers->get(self::HEADER_NAME);

        if ($value === null || !ctype_digit($value) || (int) $value <= 0) {
            throw new InvalidCurrentUserException(sprintf("%s header must be a positive integer.", self::HEADER_NAME));
        }

        return (int) $value;
    }
}
