<?php

namespace App\EventSubscriber;

use App\Attribute\IsHeaderCsrfTokenValid;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

final class HeaderCsrfTokenSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::CONTROLLER => 'onController',
        ];
    }

    public function onController(ControllerEvent $event): void
    {
        $controller = $event->getController();

        if (!\is_array($controller) || !isset($controller[0], $controller[1])) {
            return;
        }

        $reflectionMethod = new \ReflectionMethod($controller[0], $controller[1]);
        $attributes = $reflectionMethod->getAttributes(IsHeaderCsrfTokenValid::class);

        if ([] === $attributes) {
            return;
        }

        $request = $event->getRequest();

        foreach ($attributes as $attribute) {
            /** @var IsHeaderCsrfTokenValid $configuration */
            $configuration = $attribute->newInstance();
            $tokenValue = $request->headers->get($configuration->header);

            if (!\is_string($tokenValue) || '' === trim($tokenValue)) {
                throw new BadRequestException(sprintf('Missing "%s" header.', $configuration->header));
            }

            if (!$this->csrfTokenManager->isTokenValid(new CsrfToken($configuration->tokenId, $tokenValue))) {
                throw new AccessDeniedHttpException($configuration->message);
            }
        }
    }
}
