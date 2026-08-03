<?php

namespace App\Controller\Api\Request;

use Symfony\Component\Serializer\Exception\ExceptionInterface as SerializerExceptionInterface;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class RequestPayloadResolver
{
    public function __construct(
        private readonly SerializerInterface $serializer,
        private readonly ValidatorInterface $validator,
    ) {
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $requestClass
     *
     * @return T
     */
    public function resolve(string $payload, string $requestClass, string $emptyRequestMessage): object
    {
        if (trim($payload) === "") {
            throw new \InvalidArgumentException("Request body must contain valid JSON.");
        }

        try {
            $request = $this->serializer->deserialize($payload, $requestClass, "json", [
                AbstractNormalizer::ALLOW_EXTRA_ATTRIBUTES => false,
            ]);
        } catch (SerializerExceptionInterface $exception) {
            throw new \InvalidArgumentException($exception->getMessage(), previous: $exception);
        }

        if (!$request instanceof $requestClass) {
            throw new \InvalidArgumentException($emptyRequestMessage);
        }

        $violations = $this->validator->validate($request);

        if (count($violations) > 0) {
            throw new \InvalidArgumentException((string) $violations);
        }

        return $request;
    }
}
