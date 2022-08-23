<?php

namespace Makaronnik\Rpc\Test\Support;

use Amp\Promise;
use function Amp\call;

class ClientRepository implements ClientRepositoryInterface
{
    /**
     * @param int $clientId
     * @return Promise
     */
    public function getClientName(int $clientId): Promise
    {
        return call(static function () {
            return 'Boris';
        });
    }
}
