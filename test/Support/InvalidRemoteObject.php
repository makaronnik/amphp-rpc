<?php

namespace Makaronnik\Rpc\Test\Support;

class InvalidRemoteObject implements InvalidRemoteObjectInterface
{
    public function methodThatDoesNotReturnsThePromise(): bool
    {
        return true;
    }
}
