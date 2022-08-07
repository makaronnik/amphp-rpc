<?php

namespace Makaronnik\Rpc\Exceptions;

/**
 * Used to mark calls that can not safely be retried on another server, because possibly processed.
 */
class PossiblyProcessedCallException extends RpcException
{
}
