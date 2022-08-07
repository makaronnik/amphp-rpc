<?php

namespace Makaronnik\Rpc\Responses;

use Amp\Http\Server\Response;
use Makaronnik\Rpc\Enums\RpcResponseType;

final class HealthCheckRpcResponseFactory implements ResponseFactory
{
    /**
     * @return Response
     */
    public function getResponse(): Response
    {
        return (new ResponseBuilder(RpcResponseType::Success))->build();
    }
}
