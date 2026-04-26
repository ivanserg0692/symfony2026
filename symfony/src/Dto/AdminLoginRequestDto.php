<?php

namespace App\Dto;

use PixelOpen\CloudflareTurnstileBundle\Validator\CloudflareTurnstile;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class AdminLoginRequestDto
{
    public function __construct(
        #[Assert\NotBlank(message: 'Email is required.')]
        #[Assert\Email(message: 'Email must be a valid email address.')]
        public string $email = '',
        #[Assert\NotBlank(message: 'Password is required.')]
        public string $password = '',
        #[Assert\NotBlank(message: 'Captcha is required.')]
        #[CloudflareTurnstile]
        public string $turnstileToken = '',
    ) {
    }
}
