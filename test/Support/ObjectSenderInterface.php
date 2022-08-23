<?php

namespace Makaronnik\Rpc\Test\Support;

use Amp\Promise;

interface ObjectSenderInterface
{
    public function sendObject(object $object): Promise;
}
