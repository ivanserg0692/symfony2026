<?php

namespace App\Security\Csrf;

use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManager;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

final readonly class DelegatingCsrfTokenManager implements CsrfTokenManagerInterface
{
    /**
     * @param string[] $statelessTokenIds
     */
    public function __construct(
        private EnvAwareStatelessCsrfTokenManager $statelessTokenManager,
        private CsrfTokenManager $defaultTokenManager,
        private array $statelessTokenIds,
    ) {
    }

    public function getToken(string $tokenId): CsrfToken
    {
        if ($this->supportsTokenId($tokenId)) {
            return $this->statelessTokenManager->getToken($tokenId);
        }

        return $this->defaultTokenManager->getToken($tokenId);
    }

    public function refreshToken(string $tokenId): CsrfToken
    {
        if ($this->supportsTokenId($tokenId)) {
            return $this->statelessTokenManager->refreshToken($tokenId);
        }

        return $this->defaultTokenManager->refreshToken($tokenId);
    }

    public function removeToken(string $tokenId): ?string
    {
        if ($this->supportsTokenId($tokenId)) {
            return $this->statelessTokenManager->removeToken($tokenId);
        }

        return $this->defaultTokenManager->removeToken($tokenId);
    }

    public function isTokenValid(CsrfToken $token): bool
    {
        if ($this->supportsTokenId($token->getId())) {
            return $this->statelessTokenManager->isTokenValid($token);
        }

        return $this->defaultTokenManager->isTokenValid($token);
    }

    private function supportsTokenId(string $tokenId): bool
    {
        return \in_array($tokenId, $this->statelessTokenIds, true);
    }
}
