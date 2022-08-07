<?php

namespace Makaronnik\Rpc\Responses;

use Amp\Http\Server\Response;
use Makaronnik\Rpc\Enums\RpcResponseType;
use Makaronnik\Rpc\Enums\RpcMessageHeader;

final class RetryRpcResponseFactory implements ResponseFactory
{
    /**
     * @param int|null $retryWithDelayInMs
     */
    public function __construct(
        protected ?int $retryWithDelayInMs = null
    ) {
    }

    /**
     * @return Response
     */
    public function getResponse(): Response
    {
        $builder = new ResponseBuilder(RpcResponseType::Retry);

        if ($this->retryWithDelayInMs) {
            $builder->withHeader(RpcMessageHeader::RetryWithDelay, (string) $this->retryWithDelayInMs);
        }

        return $builder->build();
    }
}
