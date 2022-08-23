<?php

namespace Makaronnik\Rpc\Test\Support;

interface InvalidRemoteObjectInterface
{
    public function methodThatDoesNotReturnsThePromise(): bool;
}
