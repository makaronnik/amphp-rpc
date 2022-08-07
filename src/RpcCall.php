<?php

namespace Makaronnik\Rpc;

use Amp\Http\Client\Request;

final class RpcCall
{
    public int $retryingCount = 0;
    public int $redirectionCount = 0;

    /**
     * @param class-string $interfaceClassName
     * @param string $methodName
     * @param array $params
     * @param Request|null $request
     */
    public function __construct(
        public readonly string $interfaceClassName,
        public readonly string $methodName,
        public readonly array $params = [],
        public ?Request $request = null
    ) {
    }
}
