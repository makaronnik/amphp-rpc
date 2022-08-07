<?php

namespace Makaronnik\Rpc\Exceptions;

use Makaronnik\Rpc\Enums\RpcThrowableType;

final class RpcExceptionBuilder
{
    protected string $previousThrowableClassName = '';

    /**
     * @param RpcThrowableType $throwableType
     * @param string $rpcExceptionMessage
     * @param int $throwableCode
     */
    public function __construct(
        protected RpcThrowableType $throwableType,
        protected string $rpcExceptionMessage,
        protected int $throwableCode
    ) {
    }

    /**
     * @param string $throwableClassName
     * @return $this
     */
    public function withPreviousThrowable(string $throwableClassName): self
    {
        $this->previousThrowableClassName = $throwableClassName;

        return $this;
    }

    /**
     * @return RpcException
     */
    public function buildRpcException(): RpcException
    {
        $rpcExceptionClassName = match ($this->throwableType->value) {
            RpcThrowableType::Unprocessed->value => UnprocessedCallException::class,
            RpcThrowableType::PossiblyProcessed->value => PossiblyProcessedCallException::class,
            RpcThrowableType::Processed->value => ProcessedCallException::class
        };

        /** @var RpcException $rpcException */
        $rpcException = new $rpcExceptionClassName($this->rpcExceptionMessage, $this->throwableCode);

        if (false === empty($this->previousThrowableClassName)) {
            $rpcException->setPreviousThrowableClassName($this->previousThrowableClassName);
        }

        return $rpcException;
    }
}
