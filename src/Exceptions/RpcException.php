<?php

namespace Makaronnik\Rpc\Exceptions;

use Exception;

class RpcException extends Exception
{
    protected ?string $previousThrowableClassName = null;

    /**
     * @param string $className
     * @return void
     */
    public function setPreviousThrowableClassName(string $className): void
    {
        $this->previousThrowableClassName = $className;
    }

    /**
     * @return string|null
     */
    public function getPreviousThrowableClassName(): ?string
    {
        if (false === empty($this->previousThrowableClassName)) {
            return $this->previousThrowableClassName;
        }

        $previous = $this->getPrevious();

        if (false === \is_null($previous)) {
            return \get_class($previous);
        }

        return null;
    }
}
