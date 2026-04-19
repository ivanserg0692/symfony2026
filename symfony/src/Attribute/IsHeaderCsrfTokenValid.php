<?php

namespace App\Attribute;

#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class IsHeaderCsrfTokenValid
{
    public function __construct(
        public readonly string $tokenId,
        public readonly string $header = 'X-CSRF-Token',
        public readonly string $message = 'Invalid CSRF token.',
    ) {
    }
}
