<?php

namespace Makaronnik\Rpc;

final class RpcClientConfig
{
    public const DEFAULT_REDIRECTS_LIMIT = 10;
    public const DEFAULT_RETRYING_LIMIT = 10;
    public const DEFAULT_RETRYING_DELAY_IN_MS = 400;

    /**
     * @param int $redirectsLimit
     * @param int $retryingLimit
     * @param int $retryingDelayInMs
     * @param int|null $requestTimeoutInMs
     * @param int|null $targetEntityIdParamPosition
     */
    public function __construct(
        public readonly int  $redirectsLimit = self::DEFAULT_REDIRECTS_LIMIT,
        public readonly int  $retryingLimit = self::DEFAULT_RETRYING_LIMIT,
        public readonly int  $retryingDelayInMs = self::DEFAULT_RETRYING_DELAY_IN_MS,
        public readonly ?int $requestTimeoutInMs = null,
        public readonly ?int $targetEntityIdParamPosition = null
    ) {
    }
}
