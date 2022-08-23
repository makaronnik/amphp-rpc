<?php

namespace Makaronnik\Rpc\Test\Support;

use Amp\Promise;

interface ClientRepositoryInterface
{
    public function getClientName(int $clientId): Promise;
}
