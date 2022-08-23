<?php

namespace Makaronnik\Rpc\Test\Support;

use Amp\Promise;
use function Amp\call;

class ObjectSender implements ObjectSenderInterface
{
    /**
     * @param object $object
     * @return Promise
     */
    public function sendObject(object $object): Promise
    {
        return call(static function () {
            return true;
        });
    }
}
