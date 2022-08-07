<?php

namespace Makaronnik\Rpc;

use Throwable;
use Amp\Delayed;
use Amp\Promise;
use Amp\Http\Client\Response;
use Psr\Http\Message\UriInterface;
use Makaronnik\Rpc\Enums\RpcResponseType;
use Makaronnik\Rpc\Enums\RpcMessageHeader;
use Makaronnik\Rpc\Exceptions\UnprocessedCallException;
use Makaronnik\Rpc\Exceptions\RetriesCountExceededException;
use Makaronnik\Rpc\Exceptions\PossiblyProcessedCallException;
use Makaronnik\Rpc\Exceptions\RedirectCountExceededException;
use Makaronnik\Rpc\Exceptions\RpcExceptionFromResponseFactory;

use function Amp\call;

final class RpcResponseHandler implements RpcResponseHandlerInterface
{
    /**
     * @param Response $response
     * @param RpcCall $rpcCall
     * @param RpcClient $client
     * @return Promise<mixed>
     */
    public function handleResponse(Response $response, RpcCall $rpcCall, RpcClient $client): Promise
    {
        return call(function () use ($response, $rpcCall, $client) {
            $interfaceClassName = $rpcCall->interfaceClassName;
            $methodName = $rpcCall->methodName;
            $config = $client->config;
            $httpStatus = $response->getStatus();
            $requestToUriCacheKey = $response->getHeader(RpcMessageHeader::RequestToUriCacheKey->value);
            $rpcResponseTypeHeaderValue = $response->getHeader(RpcMessageHeader::ResponseType->value);
            $rpcResponseType = RpcResponseType::tryFrom((string) $rpcResponseTypeHeaderValue);

            if (\is_null($rpcResponseType)) {
                $client->removeItemFromRequestToUriCache($requestToUriCacheKey);

                throw new PossiblyProcessedCallException(sprintf(
                    'Failed RPC call to %s::%s() because RPC response type header value is missing or is not'
                    . ' one of these: %s.',
                    $interfaceClassName,
                    $methodName,
                    implode(', ', RpcResponseType::asArray())
                ));
            }

            if ($rpcResponseType === RpcResponseType::Throwable) {
                $client->removeItemFromRequestToUriCache($requestToUriCacheKey);

                throw yield (new RpcExceptionFromResponseFactory($response))->getException();
            }

            if ($httpStatus >= 500 || $rpcResponseType === RpcResponseType::Retry) {
                if ($rpcCall->retryingCount++ < $config->retryingLimit) {
                    $retryWithDelay = $response->getHeader(RpcMessageHeader::RetryWithDelay->value);

                    yield new Delayed($retryWithDelay ? (int) $retryWithDelay : $config->retryingDelayInMs);

                    return yield $client->call($rpcCall);
                }

                $client->removeItemFromRequestToUriCache($requestToUriCacheKey);

                if ($httpStatus >= 500) {
                    throw new UnprocessedCallException(sprintf(
                        'Failed RPC call for %s::%s(). Status code: %s. Reason: %s.',
                        $interfaceClassName,
                        $methodName,
                        $httpStatus,
                        $response->getReason()
                    ));
                }

                if ($config->retryingLimit <= 0) {
                    throw new UnprocessedCallException(sprintf(
                        'Failed RPC call for %s::%s(). This request is not allowed retries.',
                        $interfaceClassName,
                        $methodName
                    ));
                }

                throw new RetriesCountExceededException(sprintf(
                    'Failed RPC call for %s::%s(). Maximum number of retries (%s) exceeded.',
                    $interfaceClassName,
                    $methodName,
                    $config->retryingLimit
                ));
            }

            try {
                $result = yield $response->getBody()->buffer();
            } catch (Throwable $throwable) {
                $client->removeItemFromRequestToUriCache($requestToUriCacheKey);

                $errorMessage = sprintf(
                    'Failed to buffer RPC result for %s::%s(). Exception: %s. Error message: %s.',
                    $interfaceClassName,
                    $methodName,
                    \get_class($throwable),
                    $throwable->getMessage()
                );

                throw new PossiblyProcessedCallException($errorMessage, 0, $throwable);
            }

            if ($result && $response->hasHeader(RpcMessageHeader::WithSerializedContent->value)) {
                try {
                    /** @psalm-suppress MixedAssignment */
                    $result = $client->serializer->unserialize($result);
                } catch (Throwable $throwable) {
                    $client->removeItemFromRequestToUriCache($requestToUriCacheKey);

                    $errorMessage = sprintf(
                        'Failed to deserialize RPC result for %s::%s(). Exception: %s. Error message: %s.',
                        $interfaceClassName,
                        $methodName,
                        \get_class($throwable),
                        $throwable->getMessage()
                    );

                    throw new PossiblyProcessedCallException($errorMessage, 0, $throwable);
                }
            }

            if ($httpStatus !== 200) {
                $client->removeItemFromRequestToUriCache($requestToUriCacheKey);

                throw new PossiblyProcessedCallException(sprintf(
                    'Failed RPC call to %s::%s() due to an unexpected HTTP status code: %s. Reason: %s.'
                    . " \r\n " . '%s.',
                    $interfaceClassName,
                    $methodName,
                    $httpStatus,
                    $response->getReason(),
                    (string) $result
                ));
            }

            if ($rpcResponseType === RpcResponseType::Redirect) {
                if (\is_null($rpcCall->request)) {
                    throw new UnprocessedCallException(sprintf(
                        'Failed RPC call for %s::%s(). RpcCall object does not contain Request object',
                        $interfaceClassName,
                        $methodName
                    ));
                }

                if ($config->redirectsLimit <= 0) {
                    throw new UnprocessedCallException(sprintf(
                        'Failed RPC call for %s::%s(). This request is not allowed to follow redirect',
                        $interfaceClassName,
                        $methodName
                    ));
                }

                if ($rpcCall->redirectionCount++ > $config->redirectsLimit) {
                    $client->removeItemFromRequestToUriCache($requestToUriCacheKey);

                    throw new RedirectCountExceededException(sprintf(
                        'Failed RPC call for %s::%s(). Maximum number of redirects (%s) exceeded.',
                        $interfaceClassName,
                        $methodName,
                        $config->redirectsLimit
                    ));
                }

                if ($response->hasHeader(RpcMessageHeader::DirectDirection->value)) {
                    $rpcCall->request->setHeader(RpcMessageHeader::DirectDirection->value, 'true');
                }

                $redirectToHost = $response->getHeader(RpcMessageHeader::RedirectToHostOrIp->value);

                if (\is_string($redirectToHost)) {
                    $uriObject = $rpcCall->request->getUri()->withHost($redirectToHost);
                } else {
                    $client->removeItemFromRequestToUriCache($requestToUriCacheKey);

                    throw new UnprocessedCallException(sprintf(
                        'Failed RPC call for %s::%s(). %s request header not specified.',
                        $interfaceClassName,
                        $methodName,
                        RpcMessageHeader::RedirectToHostOrIp->value
                    ));
                }

                $redirectToPath = $response->getHeader(RpcMessageHeader::RedirectToPath->value);

                if (\is_string($redirectToPath)) {
                    $uriObject = $uriObject->withPath($redirectToPath);
                }

                $redirectToPort = $response->getHeader(RpcMessageHeader::RedirectToPort->value);

                if (\is_string($redirectToPort)) {
                    $uriObject = $uriObject->withPort((int) $redirectToPort);
                }

                $rpcCall->request->setUri($uriObject);

                $client->putItemToRequestToUriCache(
                    $requestToUriCacheKey,
                    $this->buildUriStringFromUriObject($uriObject)
                );

                return yield $client->call($rpcCall);
            }

            return $result;
        });
    }

    /**
     * @param UriInterface $uri
     * @return string
     */
    protected function buildUriStringFromUriObject(UriInterface $uri): string
    {
        $uriString = $uri->getScheme() . '://' . $uri->getHost();

        if ($port = $uri->getPort()) {
            $uriString .=  ':' . $port;
        }

        if ($path = $uri->getPath()) {
            $uriString .= $path;
        }

        return $uriString;
    }
}
