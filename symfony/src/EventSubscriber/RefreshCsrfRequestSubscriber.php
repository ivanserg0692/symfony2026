<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

final class RefreshCsrfRequestSubscriber implements EventSubscriberInterface
{
    private const PROTECTED_ENDPOINTS = [
        'api_v1_auth_refresh' => 'refresh',
        'api_v1_auth_logout' => 'logout',
        '/api/v1/auth/refresh' => 'refresh',
        '/api/v1/auth/logout' => 'logout',
    ];

    public function __construct(
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
        private readonly string $csrfHeaderName,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 9],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        if (!$request->isMethod('POST')) {
            return;
        }

        $route = $request->attributes->get('_route');
        $path = $request->getPathInfo();
        $tokenId = self::PROTECTED_ENDPOINTS[$route] ?? self::PROTECTED_ENDPOINTS[$path] ?? null;

        if (!\is_string($tokenId)) {
            return;
        }

        $tokenValue = $request->headers->get($this->csrfHeaderName);

        if (!\is_string($tokenValue) || '' === trim($tokenValue)) {
            throw new BadRequestException(sprintf('Missing "%s" header.', $this->csrfHeaderName));
        }

        if (!$this->csrfTokenManager->isTokenValid(new CsrfToken($tokenId, $tokenValue))) {
            throw new AccessDeniedHttpException('Invalid CSRF token.');
        }
    }
}
