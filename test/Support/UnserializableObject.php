<?php

namespace Makaronnik\Rpc\Test\Support;

class UnserializableObject
{
    public function __sleep(): array
    {
        throw new \RuntimeException('Serialization is forbidden');
    }
}
