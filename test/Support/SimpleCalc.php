<?php

namespace Makaronnik\Rpc\Test\Support;

use Amp\Promise;

use function Amp\call;

class SimpleCalc implements SimpleCalcInterface
{
    /**
     * @param int $a
     * @param int $b
     * @return Promise<int>
     */
    public function add(int $a, int $b): Promise
    {
        return call(fn (): int => $a + $b);
    }

    /**
     * @param int $a
     * @param int $b
     * @return Promise<int>
     */
    public function sub(int $a, int $b): Promise
    {
        return call(fn (): int => $a - $b);
    }

    /**
     * @param int $a
     * @param int $b
     * @return Promise<int>
     */
    public function mul(int $a, int $b): Promise
    {
        return call(fn (): int => $a * $b);
    }

    /**
     * @param int $a
     * @param int $b
     * @return Promise<float>
     */
    public function div(int $a, int $b): Promise
    {
        return call(fn (): float => $a / $b);
    }
}
