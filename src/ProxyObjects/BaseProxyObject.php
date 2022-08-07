<?php

namespace Makaronnik\Rpc\ProxyObjects;

use Amp\Promise;
use Makaronnik\Rpc\RpcCall;
use Makaronnik\Rpc\RpcClient;
use Makaronnik\Rpc\Exceptions\RpcException;

abstract class BaseProxyObject
{
    public function __construct(protected RpcClient $rpcClient)
    {
    }

    /**
     * @param string $methodName
     * @param array $params
     * @return Promise
     * @throws RpcException
     */
    final public function __call(string $methodName, array $params): Promise
    {
        if (false === \in_array($methodName, $this->getAvailableMethods(), true)) {
            throw new RpcException(sprintf(
                'Method %s is not available for %s.',
                $methodName,
                __CLASS__
            ));
        }

        return $this->rpcClient->call(new RpcCall(
            interfaceClassName: $this->getRemoteObjectInterfaceClassName(),
            methodName: $methodName,
            params: $params
        ));
    }

    /**
     * @return array
     * @noinspection ReturnTypeCanBeDeclaredInspection, PhpMissingReturnTypeInspection
     */
    abstract protected function getAvailableMethods();

    /**
     * @return class-string
     * @noinspection ReturnTypeCanBeDeclaredInspection, PhpMissingReturnTypeInspection
     */
    abstract protected function getRemoteObjectInterfaceClassName();
}
