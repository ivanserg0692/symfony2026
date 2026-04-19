<?php

namespace App\Security\Csrf;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

final readonly class EnvAwareStatelessCsrfTokenManager implements CsrfTokenManagerInterface
{
    private const TOKEN_MIN_LENGTH = 24;

    /**
     * @param string[] $trustedOrigins
     */
    public function __construct(
        private RequestStack $requestStack,
        private array $statelessTokenIds,
        private array $trustedOrigins,
        private string $cookieName,
        private string $headerName,
        private string $cookiePath,
        private string $cookieSameSite,
        private ?LoggerInterface $logger = null,
    ) {
    }

    public function getToken(string $tokenId): CsrfToken
    {
        $this->assertSupportedTokenId($tokenId);

        return new CsrfToken($tokenId, bin2hex(random_bytes(32)));
    }

    public function refreshToken(string $tokenId): CsrfToken
    {
        return $this->getToken($tokenId);
    }

    public function removeToken(string $tokenId): ?string
    {
        if (!$this->supportsTokenId($tokenId)) {
            return null;
        }

        return null;
    }

    public function isTokenValid(CsrfToken $token): bool
    {
        if (!$this->supportsTokenId($token->getId())) {
            $this->logger?->warning('CSRF validation failed: unsupported stateless token id.', [
                'token_id' => $token->getId(),
            ]);

            return false;
        }

        $request = $this->requestStack->getCurrentRequest();

        if (!$request instanceof Request) {
            $this->logger?->warning('CSRF validation failed: no current request available.');

            return false;
        }

        $tokenValue = trim($token->getValue());

        if (\strlen($tokenValue) < self::TOKEN_MIN_LENGTH) {
            $this->logger?->warning('CSRF validation failed: token is too short.');

            return false;
        }

        if ($request->headers->get($this->headerName) !== $tokenValue) {
            $this->logger?->warning('CSRF validation failed: request header does not match token.');

            return false;
        }

        $cookieName = $this->buildTokenCookieName($tokenValue);

        if ($request->cookies->get($cookieName) !== $this->cookieName) {
            $this->logger?->warning('CSRF validation failed: double-submit cookie is missing or invalid.');

            return false;
        }

        if (!$this->isTrustedRequestOrigin($request)) {
            $this->logger?->warning('CSRF validation failed: request origin is not trusted.');

            return false;
        }

        return true;
    }

    public function createTokenCookie(Request $request, string $token): Cookie
    {
        return Cookie::create(
            $this->buildTokenCookieName($token),
            $this->cookieName,
            0,
            $this->cookiePath,
            null,
            $request->isSecure(),
            true,
            false,
            $this->cookieSameSite,
        );
    }

    public function clearTokenCookies(Request $request, Response $response): void
    {
        foreach ($request->cookies->all() as $name => $value) {
            if ($value !== $this->cookieName) {
                continue;
            }

            if (!str_starts_with($name, $this->cookieName.'_')) {
                continue;
            }

            $response->headers->clearCookie(
                $name,
                $this->cookiePath,
                null,
                $request->isSecure(),
                true,
                $this->cookieSameSite,
            );
        }
    }

    public function getHeaderName(): string
    {
        return $this->headerName;
    }

    public function getCookieName(): string
    {
        return $this->cookieName;
    }

    private function buildTokenCookieName(string $token): string
    {
        return $this->cookieName.'_'.$token;
    }

    private function supportsTokenId(string $tokenId): bool
    {
        return \in_array($tokenId, $this->statelessTokenIds, true);
    }

    private function assertSupportedTokenId(string $tokenId): void
    {
        if ($this->supportsTokenId($tokenId)) {
            return;
        }

        throw new \InvalidArgumentException(sprintf('Unsupported stateless CSRF token id "%s".', $tokenId));
    }

    private function isTrustedRequestOrigin(Request $request): bool
    {
        $secFetchSite = $request->headers->get('Sec-Fetch-Site');

        if ('same-origin' === $secFetchSite) {
            return true;
        }

        $targetOrigin = $request->getSchemeAndHttpHost();

        foreach (['Origin', 'Referer'] as $header) {
            $source = $request->headers->get($header);

            if (!\is_string($source) || '' === trim($source)) {
                continue;
            }

            $origin = $this->extractOrigin($source);

            if (null === $origin) {
                continue;
            }

            if ($origin === $targetOrigin || \in_array($origin, $this->trustedOrigins, true)) {
                return true;
            }

            return false;
        }

        return false;
    }

    private function extractOrigin(string $value): ?string
    {
        $parts = parse_url($value);

        if (!\is_array($parts) || !isset($parts['scheme'], $parts['host'])) {
            return null;
        }

        $origin = sprintf('%s://%s', $parts['scheme'], $parts['host']);

        if (isset($parts['port'])) {
            $origin .= ':'.$parts['port'];
        }

        return $origin;
    }
}
