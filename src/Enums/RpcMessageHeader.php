<?php

namespace Makaronnik\Rpc\Enums;

enum RpcMessageHeader: string
{
    case ResponseType = 'rpc-response-type';

    case WithSerializedContent = 'rpc-serialized-content';

    case ThrowableType = 'rpc-throwable-type';
    case ThrowableCode = 'rpc-throwable-code';
    case ThrowableClass = 'rpc-throwable-class';

    case RpcRemoteInterfaceClassName = 'rpc-interface-class-name';
    case RpcRemoteMethodName = 'rpc-method-name';

    case RedirectToHostOrIp = 'redirect-to-host-or-ip';
    case RedirectToPath = 'redirect-to-path';
    case RedirectToPort = 'redirect-to-port';
    case DirectDirection = 'directDirection';

    case RetryWithDelay = 'retry-with-delay';

    case TargetEntity = 'targetEntity';

    case RequestToUriCacheKey = 'requestToUriCacheKey';
}
