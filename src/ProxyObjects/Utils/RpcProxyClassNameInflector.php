<?php

namespace Makaronnik\Rpc\ProxyObjects\Utils;

final class RpcProxyClassNameInflector
{
    protected const GENERATED_PROXY_OBJECTS_NAMESPACE = 'Makaronnik\\Rpc\\GeneratedProxy';
    protected const GENERATED_PROXY_OBJECTS_CLASS_NAME_POSTFIX = 'GeneratedProxy';

    /**
     * @return string
     */
    public static function getNamespace(): string
    {
        return self::GENERATED_PROXY_OBJECTS_NAMESPACE;
    }

    /**
     * @param class-string $interfaceName
     * @return string
     */
    public static function getProxyShortClassName(string $interfaceName): string
    {
        return self::getShortClassName($interfaceName) . self::GENERATED_PROXY_OBJECTS_CLASS_NAME_POSTFIX;
    }

    /**
     * @param class-string $interfaceName
     * @return string
     */
    public static function getProxyFullClassName(string $interfaceName): string
    {
        return self::getNamespace() . '\\' . self::getProxyShortClassName($interfaceName);
    }

    /**
     * @param class-string $interfaceName
     * @param string $interfaceFileContent
     * @param string $packageVersionReference
     * @return string
     */
    public static function getProxyFileName(
        string $interfaceName,
        string $interfaceFileContent,
        string $packageVersionReference
    ): string {
        return sprintf(
            '%s%s.php',
            self::getProxyShortClassName($interfaceName),
            self::getProxyFileNamePrefix($interfaceFileContent, $packageVersionReference)
        );
    }
    /**
     * @param class-string $fullClassName
     * @return string
     */
    protected static function getShortClassName(string $fullClassName): string
    {
        $lastBackslashPosition = strrpos($fullClassName, '\\');

        if (false === $lastBackslashPosition) {
            return $fullClassName;
        }

        return substr($fullClassName, $lastBackslashPosition + 1);
    }

    /**
     * @param string $interfaceFileContent
     * @param string $packageVersionReference
     * @return string
     */
    protected static function getProxyFileNamePrefix(
        string $interfaceFileContent,
        string $packageVersionReference
    ): string {
        return substr(
            base_convert(md5($interfaceFileContent . $packageVersionReference), 16, 32),
            0,
            12
        );
    }
}
