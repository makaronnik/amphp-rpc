<?php

namespace Makaronnik\Rpc;

use Throwable;
use Amp\Promise;
use Amp\Http\Server\Request;
use Amp\Http\Server\Response;
use Amp\Serialization\Serializer;
use Amp\Http\Server\RequestHandler;
use Makaronnik\Rpc\Enums\RpcMessageHeader;
use Makaronnik\Rpc\Enums\RpcThrowableType;
use Makaronnik\Rpc\Exceptions\RpcException;
use Amp\Serialization\SerializationException;
use Makaronnik\Rpc\Exceptions\UnprocessedCallException;
use Makaronnik\Rpc\Responses\SuccessRpcResponseFactory;
use Makaronnik\Rpc\Responses\ThrowableRpcResponseFactory;
use Amp\Http\Client\Connection\UnprocessedRequestException;
use Makaronnik\Rpc\Responses\HealthCheckRpcResponseFactory;
use function Amp\call;

final class RpcRequestHandler implements RequestHandler
{
    /** @var callable[] */
    protected array $interceptors = [];

    /**
     * @param Serializer $serializer
     * @param RpcRegistry $rpcRegistry
     * @param string|null $healthCheckRoute
     */
    public function __construct(
        protected readonly Serializer $serializer,
        protected readonly RpcRegistry $rpcRegistry,
        protected readonly ?string $healthCheckRoute = null
    ) {
    }

    /**
     * @param callable $interceptor (Request $request, string $interceptorUniqId) MUST return Response OR Request
     * @return string
     */
    public function registerInterceptor(callable $interceptor): string
    {
        $interceptorUniqId = uniqid('', true);

        $this->interceptors[$interceptorUniqId] = $interceptor;

        return $interceptorUniqId;
    }

    /**
     * @param string $interceptorUniqId
     * @return void
     */
    public function unregisterInterceptor(string $interceptorUniqId): void
    {
        unset($this->interceptors[$interceptorUniqId]);
    }

    /**
     * @param Request $request
     * @return Promise<Response>
     * @psalm-suppress MixedArgument, MixedOperand, MixedMethodCall, DocblockTypeContradiction
     */
    public function handleRequest(Request $request): Promise
    {
        return call(function () use ($request) {
            if (isset($this->healthCheckRoute) && $request->getUri()->getPath() === ('/' . $this->healthCheckRoute)) {
                return (new HealthCheckRpcResponseFactory())->getResponse();
            }

            if ($request->getMethod() !== 'POST') {
                $exception = new RpcException('HTTP Request METHOD MUST be POST.', 405);

                return (new ThrowableRpcResponseFactory(
                    $this->serializer,
                    $exception,
                    RpcThrowableType::Unprocessed
                ))->getResponse();
            }

            if (false === empty($this->interceptors)) {
                foreach ($this->interceptors as $interceptorUniqId => $interceptor) {
                    try {
                        /** @psalm-suppress MixedArgumentTypeCoercion */
                        $result = yield call($interceptor, $request, $interceptorUniqId);

                        if ($result instanceof Response) {
                            return $result;
                        }

                        if (false === $result instanceof Request) {
                            throw new RpcException('Interceptor MUST return Response OR Request.');
                        }

                        $request = $result;
                    } catch (UnprocessedCallException|UnprocessedRequestException $exception) {
                        return (new ThrowableRpcResponseFactory(
                            $this->serializer,
                            $exception,
                            RpcThrowableType::Unprocessed
                        ))->getResponse();
                    } catch (Throwable $exception) {
                        return (new ThrowableRpcResponseFactory(
                            $this->serializer,
                            $exception,
                            RpcThrowableType::PossiblyProcessed
                        ))->getResponse();
                    }
                }
            }

            try {
                $serializedParams = yield $request->getBody()->buffer();

                try {
                    /** @var array|string|null $params */
                    $params = $this->serializer->unserialize($serializedParams);
                } catch (SerializationException $exception) {
                    throw new RpcException('Failed to decode RPC parameters.', 0, $exception);
                }

                /** @var class-string|null $interface */
                $interface = $request->getHeader(RpcMessageHeader::RpcRemoteInterfaceClassName->value);
                $method = $request->getHeader(RpcMessageHeader::RpcRemoteMethodName->value);

                if (
                    empty($interface) ||
                    empty($method) ||
                    false === method_exists($interface, $method)
                ) {
                    throw new RpcException(sprintf(
                        '%s::%s() not found.',
                        ($interface ?? '?interface'),
                        ($method ?? '?method')
                    ));
                }

                if (false === \is_array($params)) {
                    throw new RpcException(sprintf(
                        'Invalid parameter format (%s). Array expected.',
                        get_debug_type($params)
                    ));
                }

                $remoteObjectClassName = $this->rpcRegistry->getRemoteObjectClassName($interface);

                if (\is_null($remoteObjectClassName)) {
                    throw new RpcException(sprintf(
                        'Failed to call %1$s::%2$s(), because %1$s is not registered.',
                        $interface,
                        $method
                    ));
                }

                if (false === class_exists($remoteObjectClassName)) {
                    throw new RpcException(sprintf(
                        'Failed to call %1$s::%2$s(), because %1$s is not exists.',
                        $remoteObjectClassName,
                        $method
                    ));
                }

                if (false === method_exists($remoteObjectClassName, $method)) {
                    throw new RpcException(sprintf(
                        'Failed to call %1$s::%2$s(), because %2$s is not exists in %1$s.',
                        $remoteObjectClassName,
                        $method
                    ));
                }
            } catch (Throwable $throwable) {
                return (new ThrowableRpcResponseFactory(
                    $this->serializer,
                    $throwable,
                    RpcThrowableType::Unprocessed
                ))->getResponse();
            }

            $promise = (new $remoteObjectClassName)->{$method}(...$params);

            if (false === $promise instanceof Promise) {
                $exception = new RpcException(sprintf(
                    'RPC calls must always return an instance of %s, got %s.',
                    Promise::class,
                    get_debug_type($promise)
                ));

                return (new ThrowableRpcResponseFactory(
                    $this->serializer,
                    $exception,
                    RpcThrowableType::Unprocessed
                ))->getResponse();
            }

            try {
                /** @psalm-suppress MixedAssignment */
                $result = yield $promise;

                if ($result instanceof Response) {
                    return $result;
                }

                return $this->makeSuccessResponse($result);
            } catch (Throwable $throwable) {
                return (new ThrowableRpcResponseFactory(
                    $this->serializer,
                    $throwable,
                    RpcThrowableType::PossiblyProcessed
                ))->getResponse();
            }
        });
    }

    /**
     * @param mixed $returnValue
     * @return Response
     */
    protected function makeSuccessResponse(mixed $returnValue): Response
    {
        try {
            $serializedResult = $this->serializer->serialize($returnValue);
        } catch (SerializationException $exception) {
            $errorMessage = sprintf(
                'Failed to serialize RPC return value, due an error: %s.',
                $exception->getMessage()
            );

            return (new ThrowableRpcResponseFactory(
                $this->serializer,
                new RpcException($errorMessage, 0, $exception),
                RpcThrowableType::Processed
            ))->getResponse();
        }

        return (new SuccessRpcResponseFactory($serializedResult))->getResponse();
    }
}
