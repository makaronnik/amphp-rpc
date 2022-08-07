<?php

namespace Makaronnik\Rpc\Exceptions;

/**
 * Used to mark calls that can safely be retried on another server.
 */
class UnprocessedCallException extends RpcException
{
}
