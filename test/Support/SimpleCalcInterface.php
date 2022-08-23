<?php

namespace Makaronnik\Rpc\Test\Support;

use Amp\Promise;

interface SimpleCalcInterface
{
    /**
     * @param int $a
     * @param int $b
     * @return Promise<int>
     */
    public function add(int $a, int $b): Promise;

    /**
     * @param int $a
     * @param int $b
     * @return Promise<int>
     */
    public function sub(int $a, int $b): Promise;

    /**
     * @param int $a
     * @param int $b
     * @return Promise<int>
     */
    public function mul(int $a, int $b): Promise;

    /**
     * @param int $a
     * @param int $b
     * @return Promise<float>
     */
    public function div(int $a, int $b): Promise;
}
