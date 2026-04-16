<?php

namespace App\Dto;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

#[OA\Schema(
    schema: 'AuthLoginRequestDto',
    type: 'object',
    required: ['email', 'password'],
    description: 'Credentials payload for JWT login.',
)]
final readonly class AuthLoginRequestDto
{
    public function __construct(
        #[Assert\NotBlank(message: 'Email is required.')]
        #[Assert\Email(message: 'Email must be a valid email address.')]
        #[OA\Property(type: 'string', format: 'email', example: 'user@example.com')]
        public string $email = '',
        #[Assert\NotBlank(message: 'Password is required.')]
        #[OA\Property(type: 'string', format: 'password', example: 'password123')]
        public string $password = '',
    ) {
    }
}
