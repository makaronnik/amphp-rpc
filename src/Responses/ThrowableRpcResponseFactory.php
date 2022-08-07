<?php

namespace Makaronnik\Rpc\Responses;

use Throwable;
use Amp\Http\Server\Response;
use Amp\Serialization\Serializer;
use Makaronnik\Rpc\Enums\RpcResponseType;
use Makaronnik\Rpc\Enums\RpcMessageHeader;
use Makaronnik\Rpc\Enums\RpcThrowableType;

final class ThrowableRpcResponseFactory implements ResponseFactory
{
    /**
     * @param Serializer $serializer
     * @param Throwable $throwable
     * @param RpcThrowableType $throwableType
     */
    public function __construct(
        protected Serializer       $serializer,
        protected Throwable        $throwable,
        protected RpcThrowableType $throwableType
    ) {
    }

    /**
     * @return Response
     */
    public function getResponse(): Response
    {
        $builder = (new ResponseBuilder(RpcResponseType::Throwable))
            ->withHeader(RpcMessageHeader::ThrowableType, $this->throwableType->value)
            ->withHeader(RpcMessageHeader::ThrowableCode, $this->throwable->getCode())
            ->withHeader(RpcMessageHeader::ThrowableClass, \get_class($this->throwable));

        $builder->withContent($this->throwable->getMessage());

        return $builder->build();
    }
}
