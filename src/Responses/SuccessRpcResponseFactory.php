<?php

namespace Makaronnik\Rpc\Responses;

use Amp\Http\Server\Response;
use Makaronnik\Rpc\Enums\RpcResponseType;

final class SuccessRpcResponseFactory implements ResponseFactory
{
    /**
     * @param string $serializedResult
     */
    public function __construct(protected string $serializedResult)
    {
    }

    /**
     * @return Response
     */
    public function getResponse(): Response
    {
        return (new ResponseBuilder(RpcResponseType::Success))
            ->withContent($this->serializedResult)
            ->withSerializedContent()
            ->build();
    }
}
