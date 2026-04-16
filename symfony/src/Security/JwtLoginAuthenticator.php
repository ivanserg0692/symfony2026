<?php

namespace App\Security;

use App\Dto\AuthLoginRequestDto;
use Lexik\Bundle\JWTAuthenticationBundle\Security\Http\Authentication\AuthenticationFailureHandler;
use Lexik\Bundle\JWTAuthenticationBundle\Security\Http\Authentication\AuthenticationSuccessHandler;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\Exception\ExceptionInterface as SerializerExceptionInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\SecurityRequestAttributes;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class JwtLoginAuthenticator extends AbstractAuthenticator
{
    public function __construct(
        private readonly AuthenticationSuccessHandler $successHandler,
        private readonly AuthenticationFailureHandler $failureHandler,
        private readonly SerializerInterface $serializer,
        private readonly ValidatorInterface $validator,
    ) {
    }

    public function supports(Request $request): ?bool
    {
        return $request->isMethod('POST') && 'api_v1_auth_login' === $request->attributes->get('_route');
    }

    public function authenticate(Request $request): Passport
    {
        try {
            /** @var AuthLoginRequestDto $payload */
            $payload = $this->serializer->deserialize($request->getContent(), AuthLoginRequestDto::class, 'json');
        } catch (SerializerExceptionInterface|\JsonException) {
            throw new CustomUserMessageAuthenticationException('Invalid JSON payload.');
        }

        $violations = $this->validator->validate($payload);

        if (0 !== \count($violations)) {
            throw new CustomUserMessageAuthenticationException($this->buildValidationMessage($violations));
        }

        $request->attributes->set(SecurityRequestAttributes::LAST_USERNAME, $payload->email);

        return new Passport(
            new UserBadge($payload->email),
            new PasswordCredentials($payload->password),
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return $this->successHandler->onAuthenticationSuccess($request, $token);
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return $this->failureHandler->onAuthenticationFailure($request, $exception);
    }

    private function buildValidationMessage(ConstraintViolationListInterface $violations): string
    {
        return $violations[0]?->getMessage() ?? 'Invalid login payload.';
    }
}
