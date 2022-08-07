<?php

namespace Makaronnik\Rpc\Enums;

enum RpcResponseType: string
{
    case Success = 'success';
    case Retry = 'retry';
    case Redirect = 'redirect';
    case Throwable = 'throwable';

    /**
     * @param bool $withNamedKeys
     * @return string[]
     */
    public static function asArray(bool $withNamedKeys = false): array
    {
        $result = [];

        foreach (self::cases() as $case) {
            $value = $case->value;

            if ($withNamedKeys) {
                $result[$case->name] = $value;
            } else {
                $result[] = $value;
            }
        }

        return $result;
    }
}
