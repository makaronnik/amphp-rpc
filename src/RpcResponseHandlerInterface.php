<?php

namespace Makaronnik\Rpc;

use Amp\Promise;
use Amp\Http\Client\Response;

interface RpcResponseHandlerInterface
{
    /**
     * @param Response $response
     * @param RpcCall $rpcCall
     * @param RpcClient $client
     * @return Promise
     */
    public function handleResponse(Response $response, RpcCall $rpcCall, RpcClient $client): Promise;
}
