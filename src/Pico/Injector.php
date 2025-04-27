<?php

namespace PicoPHP;

use ReflectionClass;

class Injector
{
    private static $instances = [];

    /**
     * Instancie dynamiquement une classe et retourne son type correct.
     *
     * @template T
     * @param class-string<T> $class
     * @return T
     */
    public static function Inject($class)
    {
        $isSingleton = is_subclass_of($class, asSingleton::class);
        if ($isSingleton && isset(self::$instances[$class])) return self::$instances[$class];

        $ref = new ReflectionClass($class);
        $constructor = $ref->getConstructor();
        $params = [];
        if ($constructor) {
            $params = array_map(
                fn($param) => self::Inject((string) $param->getType()),
                $constructor->getParameters()
            );
        }
        $instance = new $class(...$params);

        if ($isSingleton)  return self::$instances[$class] = $instance;

        return $instance;
    }
}
