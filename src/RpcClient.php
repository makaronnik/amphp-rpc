<?php

namespace Makaronnik\Rpc;

use Throwable;
use Amp\Delayed;
use Amp\Promise;
use cash\LRUCache;
use Amp\Dns\DnsException;
use Amp\Http\Client\Request;
use Amp\Http\Client\HttpClient;
use Amp\Serialization\Serializer;
use Amp\Http\Client\HttpException;
use Psr\Http\Message\UriInterface;
use Makaronnik\Rpc\Dns\DnsResolver;
use Makaronnik\Rpc\Enums\RpcMessageHeader;
use Makaronnik\Rpc\Exceptions\RpcException;
use Makaronnik\Rpc\Exceptions\UnprocessedCallException;
use Amp\Http\Client\Connection\UnprocessedRequestException;
use Makaronnik\Rpc\Exceptions\PossiblyProcessedCallException;
use function Amp\call;

final class RpcClient
{
    /**
     * @param string|UriInterface $uri
     * @param RpcClientConfig $config
     * @param RpcResponseHandlerInterface $responseHandler
     * @param Serializer $serializer
     * @param HttpClient $httpClient
     * @param DnsResolver $dnsResolver
     * @param LRUCache $requestToUriCache
     */
    public function __construct(
        protected string|UriInterface         $uri,
        public readonly RpcClientConfig       $config,
        protected RpcResponseHandlerInterface $responseHandler,
        public readonly Serializer            $serializer,
        protected HttpClient                  $httpClient,
        protected DnsResolver                 $dnsResolver,
        protected LRUCache                    $requestToUriCache
    ) {
    }

    /**
     * @param RpcCall $rpcCall
     * @return Promise<mixed>
     * @throws RpcException
     * @noinspection PhpDocRedundantThrowsInspection
     */
    public function call(RpcCall $rpcCall): Promise
    {
        return call(function () use ($rpcCall) {
            if (isset($rpcCall->request)) {
                $request = $rpcCall->request;
            } else {
                try {
                    $request = $rpcCall->request = yield $this->buildRequest($rpcCall);
                } catch (Throwable $throwable) {
                    $errorMessage = sprintf(
                        'Failed RPC call for %s::%s() due to request building fail.'
                        . ' Throwable: %s. Error message: %s.',
                        $rpcCall->interfaceClassName,
                        $rpcCall->methodName,
                        \get_class($throwable),
                        $throwable->getMessage()
                    );

                    throw new UnprocessedCallException($errorMessage, 0, $throwable);
                }
            }

            $requestToUriCacheKey = $request->getHeader(RpcMessageHeader::RequestToUriCacheKey->value);

            try {
                $response = yield $this->httpClient->request($request);

                return yield $this->responseHandler->handleResponse($response, $rpcCall, $this);
            } catch (RpcException $exception) {
                $this->removeItemFromRequestToUriCache($requestToUriCacheKey);

                throw $exception;
            } catch (UnprocessedRequestException $exception) {
                $this->removeItemFromRequestToUriCache($requestToUriCacheKey);

                if ($rpcCall->retryingCount++ < $this->config->retryingLimit) {
                    yield new Delayed($this->config->retryingDelayInMs);

                    return yield $this->call($rpcCall);
                }

                $errorMessage = sprintf(
                    'Failed RPC call due to an HTTP communication failure for %s::%s().'
                    . ' Exception: \Amp\Http\Client\Connection\UnprocessedRequestException. Error message: %s.',
                    $rpcCall->interfaceClassName,
                    $rpcCall->methodName,
                    $exception->getMessage()
                );

                throw new UnprocessedCallException($errorMessage, 0, $exception);
            } catch (HttpException $exception) {
                $this->removeItemFromRequestToUriCache($requestToUriCacheKey);

                $errorMessage = sprintf(
                    'Failed RPC call due to an HTTP communication failure for %s::%s().'
                    . ' Exception: Amp\Http\Client\HttpException. Error message: %s.',
                    $rpcCall->interfaceClassName,
                    $rpcCall->methodName,
                    $exception->getMessage()
                );

                throw new PossiblyProcessedCallException($errorMessage, 0, $exception);
            } catch (Throwable $throwable) {
                $this->removeItemFromRequestToUriCache($requestToUriCacheKey);

                $errorMessage = sprintf(
                    'Failed RPC call due to an HTTP communication failure for %s::%s(). Throwable: %s.'
                    . ' Error message: %s.',
                    $rpcCall->interfaceClassName,
                    $rpcCall->methodName,
                    \get_class($throwable),
                    $throwable->getMessage()
                );

                throw new PossiblyProcessedCallException($errorMessage, 0, $throwable);
            }
        });
    }

    /**
     * @param string $key
     * @return string|null
     */
    public function getItemFromRequestToUriCache(string $key): ?string
    {
        /** @psalm-suppress MixedAssignment */
        $value = $this->requestToUriCache->get($key);

        return $value ? (string) $value : null;
    }

    /**
     * @param string|null $key
     * @param string $value
     * @return void
     */
    public function putItemToRequestToUriCache(?string $key, string $value): void
    {
        if (false === \is_null($key)) {
            $this->requestToUriCache->put($key, $value);
        }
    }

    /**
     * @param string|null $key
     * @return void
     */
    public function removeItemFromRequestToUriCache(?string $key): void
    {
        if (false === \is_null($key)) {
            $this->requestToUriCache->remove($key);
        }
    }

    /**
     * @param string $hostName
     * @param class-string $interfaceClassName
     * @param string $methodName
     * @param string|null $targetEntityId
     * @return string
     */
    public function buildRequestToUriCacheKey(
        string $hostName,
        string $interfaceClassName,
        string $methodName,
        ?string $targetEntityId = null
    ): string {
        return sprintf(
            '%s->%s::%s%s',
            $hostName,
            $interfaceClassName,
            $methodName,
            $targetEntityId ? "_$targetEntityId" : ''
        );
    }

    /**
     * @param RpcCall $rpcCall
     * @return Promise<Request>
     * @throws RpcException
     * @throws DnsException
     * @noinspection PhpDocRedundantThrowsInspection
     */
    protected function buildRequest(RpcCall $rpcCall): Promise
    {
        return call(function () use ($rpcCall) {
            $request = new Request($this->uri, 'POST');

            try {
                $request->setBody($this->serializer->serialize($rpcCall->params));
            } catch (Throwable $exception) {
                $errorMessage = sprintf(
                    'Failed to serialize RPC parameters for %s::%s() due an error: %s.',
                    $rpcCall->interfaceClassName,
                    $rpcCall->methodName,
                    $exception->getMessage()
                );

                throw new RpcException($errorMessage, 0, $exception);
            }

            $targetEntityId = null;
            $targetEntityIdParamPosition = $this->config->targetEntityIdParamPosition;

            if (isset($targetEntityIdParamPosition, $rpcCall->params[$targetEntityIdParamPosition])) {
                $targetEntityId = (string) $rpcCall->params[$targetEntityIdParamPosition];
                $request->setHeader(RpcMessageHeader::TargetEntity->value, $targetEntityId);
            }

            $host = $request->getUri()->getHost();

            if (false === filter_var($host, FILTER_VALIDATE_IP)) {
                $requestToUriCacheKey = $this->buildRequestToUriCacheKey(
                    $host,
                    $rpcCall->interfaceClassName,
                    $rpcCall->methodName,
                    $targetEntityId
                );

                $request->setHeader(RpcMessageHeader::RequestToUriCacheKey->value, $requestToUriCacheKey);

                if (\is_string($uriFromCache = $this->getItemFromRequestToUriCache($requestToUriCacheKey))) {
                    $request->setUri($uriFromCache);
                } else {
                    $serverIp = yield $this->dnsResolver->getFirstARecord($host);
                    $request->setUri($request->getUri()->withHost($serverIp));
                }
            }

            $requestTimeoutInMs = $this->config->requestTimeoutInMs;

            if (isset($requestTimeoutInMs) && $requestTimeoutInMs > 0) {
                $request->setTransferTimeout($requestTimeoutInMs);
                $request->setInactivityTimeout($requestTimeoutInMs);
                $request->setTcpConnectTimeout((int) ($requestTimeoutInMs / 2));
            }

            $request->setHeader(RpcMessageHeader::RpcRemoteInterfaceClassName->value, $rpcCall->interfaceClassName);
            $request->setHeader(RpcMessageHeader::RpcRemoteMethodName->value, $rpcCall->methodName);

            return $request;
        });
    }
}
