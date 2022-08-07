<?php

namespace Makaronnik\Rpc\Dns;

use Amp\Promise;
use Amp\Dns\Config;
use Amp\Dns\HostLoader;
use Amp\Dns\ConfigLoader;
use Amp\Dns\ConfigException;
use Amp\Dns\UnixConfigLoader;
use Amp\Dns\WindowsConfigLoader;
use function Amp\call;

final class DnsConfigLoader implements ConfigLoader
{
    protected ConfigLoader $loader;

    /**
     * @param int $timeoutInMs
     * @param int $attempts
     * @param string $path
     * @param HostLoader|null $hostLoader
     */
    public function __construct(
        protected int $timeoutInMs,
        protected int $attempts,
        protected string $path = "/etc/resolv.conf",
        protected ?HostLoader $hostLoader = null
    ) {
        $this->loader = stripos(PHP_OS_FAMILY, 'Windows') === 0
            ? new WindowsConfigLoader($this->hostLoader)
            : new UnixConfigLoader($this->path, $this->hostLoader);
    }

    /**
     * @return Promise<Config>
     * @throws ConfigException
     * @noinspection PhpDocRedundantThrowsInspection
     */
    public function loadConfig(): Promise
    {
        return call(function () {
            /** @var Config $baseConfig */
            $baseConfig = yield $this->loader->loadConfig();

            return (new Config(
                $baseConfig->getNameservers(),
                $baseConfig->getKnownHosts(),
                $this->timeoutInMs,
                $this->attempts
            ))
                ->withRotationEnabled($baseConfig->isRotationEnabled())
                ->withSearchList($baseConfig->getSearchList())
                ->withNdots($baseConfig->getNdots());
        });
    }
}
