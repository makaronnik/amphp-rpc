<?php

namespace Makaronnik\Rpc\Responses;

use Amp\Http\Server\Response;

interface ResponseFactory
{
    /**
     * @return Response
     */
    public function getResponse(): Response;
}
