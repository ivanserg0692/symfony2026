<?php

namespace App\RoadRunner\Grpc;

use Google\Protobuf\Internal\Message;
use Psr\Log\LoggerInterface;
use Spiral\RoadRunner\GRPC\ContextInterface;
use Spiral\RoadRunner\GRPC\InvokerInterface;
use Spiral\RoadRunner\GRPC\Method;
use Spiral\RoadRunner\GRPC\ServiceInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Profiler\Profiler;

final readonly class ProfilingInvoker implements InvokerInterface
{
    public function __construct(
        private InvokerInterface $inner,
        private Profiler $profiler,
        private ?LoggerInterface $logger = null,
    ) {
    }

    public function invoke(
        ServiceInterface $service,
        Method $method,
        ContextInterface $ctx,
        string|Message|null $input,
    ): string {
        $serviceName = $this->resolveServiceName($service);
        $request = $this->createRequest($serviceName, $method, $input);

        try {
            $grpcResponse = $this->inner->invoke($service, $method, $ctx, $input);
        } catch (\Throwable $exception) {
            $response = new Response('', Response::HTTP_INTERNAL_SERVER_ERROR, [
                'content-type' => 'application/grpc+json',
            ]);
            $this->collect($request, $response, $exception);

            throw $exception;
        }

        $response = new Response($this->serializeOutput($method, $grpcResponse), Response::HTTP_OK, [
            'content-type' => 'application/grpc+json',
        ]);
        $this->collect($request, $response);

        return $grpcResponse;
    }

    private function createRequest(string $serviceName, Method $method, string|Message|null $input): Request
    {
        $request = Request::create(
            sprintf('/grpc/%s/%s', $serviceName, $method->name),
            'POST',
            server: ['CONTENT_TYPE' => 'application/grpc+json'],
            content: $this->serializeInput($method, $input),
        );
        $request->attributes->set('_controller', $serviceName.'::'.$method->name);
        $request->attributes->set('_grpc_service', $serviceName);
        $request->attributes->set('_grpc_method', $method->name);
        $request->attributes->set('_grpc_request_type', $method->inputType);
        $request->attributes->set('_grpc_response_type', $method->outputType);

        return $request;
    }

    private function serializeInput(Method $method, string|Message|null $input): string
    {
        if ($input instanceof Message) {
            return $input->serializeToJsonString();
        }

        if ($input === null) {
            return '';
        }

        return $this->serializeMessage($method->inputType, $input);
    }

    private function serializeOutput(Method $method, string $output): string
    {
        return $this->serializeMessage($method->outputType, $output);
    }

    /**
     * @param class-string<Message> $messageClass
     */
    private function serializeMessage(string $messageClass, string $payload): string
    {
        try {
            $message = new $messageClass();
            $message->mergeFromString($payload);

            return $message->serializeToJsonString();
        } catch (\Throwable) {
            return '';
        }
    }

    private function resolveServiceName(ServiceInterface $service): string
    {
        $serviceReflection = new \ReflectionObject($service);

        foreach ($serviceReflection->getInterfaceNames() as $interfaceName) {
            if (!is_a($interfaceName, ServiceInterface::class, true)) {
                continue;
            }

            $interfaceReflection = new \ReflectionClass($interfaceName);

            if ($interfaceReflection->hasConstant('NAME')) {
                $name = $interfaceReflection->getConstant('NAME');

                if (is_string($name) && $name !== '') {
                    return $name;
                }
            }
        }

        return $service::class;
    }

    private function collect(Request $request, Response $response, ?\Throwable $exception = null): void
    {
        $profile = $this->profiler->collect($request, $response, $exception);

        if ($profile === null) {
            return;
        }

        $this->profiler->saveProfile($profile);

        $this->logger?->debug('gRPC profiler token', [
            'token' => $profile->getToken(),
            'path' => $request->getPathInfo(),
        ]);
    }
}
