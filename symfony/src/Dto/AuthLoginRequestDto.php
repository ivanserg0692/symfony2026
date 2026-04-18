<?php

namespace App\Dto;

use OpenApi\Attributes as OA;
use PixelOpen\CloudflareTurnstileBundle\Validator\CloudflareTurnstile;
use Symfony\Component\Validator\Constraints as Assert;

#[OA\Schema(
    schema: 'AuthLoginRequestDto',
    type: 'object',
    required: ['email', 'password', 'turnstileToken'],
    description: 'Credentials payload for JWT login protected by Cloudflare Turnstile.',
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
        #[Assert\NotBlank(message: 'Turnstile token is required.')]
        #[CloudflareTurnstile]
        #[OA\Property(
            type: 'string',
            example: '0.zrSnRHO7h0HwSJ4v4f9Z9w7uYxY4yG3K0B9Yk9Tt4g8gqJmM6m0l3',
            description: 'Cloudflare Turnstile response token obtained on the frontend.'
        )]
        public string $turnstileToken = '',
    ) {
    }
}
