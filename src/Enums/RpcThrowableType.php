<?php

namespace Makaronnik\Rpc\Enums;

enum RpcThrowableType: string
{
    case Unprocessed = 'unprocessed';
    case PossiblyProcessed = 'possiblyProcessed';
    case Processed = 'processed';
}
