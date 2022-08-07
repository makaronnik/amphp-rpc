<?php

namespace Makaronnik\Rpc\Exceptions;

use Amp\Promise;
use Amp\Http\Client\Response;
use Makaronnik\Rpc\Enums\RpcMessageHeader;
use Makaronnik\Rpc\Enums\RpcThrowableType;
use function Amp\call;

final class RpcExceptionFromResponseFactory
{
    public function __construct(protected Response $response)
    {
    }

    /**
     * @return Promise<RpcException>
     */
    public function getException(): Promise
    {
        return call(function () {
            $errorMessage = '';

            try {
                $throwableType = RpcThrowableType::from(
                    $this->response->getHeader(RpcMessageHeader::ThrowableType->value) ?? ''
                );
            } catch (\Throwable) {
                $throwableType = RpcThrowableType::PossiblyProcessed;
                $errorMessage .= 'Failed to get throwable type from response header. ';
            }

            $throwableCode = $this->response->getHeader(RpcMessageHeader::ThrowableCode->value);

            if (\is_string($throwableCode)) {
                $throwableCode = (int) $throwableCode;
            } else {
                $throwableCode = 0;
            }

            $throwableMessage = yield $this->response->getBody()->read();

            if (empty($throwableMessage)) {
                $throwableMessage = 'Throwable message is empty';
            }

            $errorMessage .= sprintf('Throwable message: %s. ', $throwableMessage);

            $throwableClass = $this->response->getHeader(RpcMessageHeader::ThrowableClass->value);

            if (\is_string($throwableClass)) {
                $errorMessage = sprintf('Throwable class: %s. ', $throwableClass) . $errorMessage;
                $exceptionBuilder = (new RpcExceptionBuilder($throwableType, $errorMessage, $throwableCode))
                    ->withPreviousThrowable($throwableClass);
            } else {
                $errorMessage .= 'Failed to get throwable class from response header. ';
                $exceptionBuilder = (new RpcExceptionBuilder($throwableType, $errorMessage, $throwableCode));
            }

            return $exceptionBuilder->buildRpcException();
        });
    }
}
