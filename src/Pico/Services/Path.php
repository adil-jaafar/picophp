<?php

namespace PicoPHP\Services;

use ArrayAccess;
use JsonSerializable;
use PicoPHP\asSingleton;

class Path implements ArrayAccess, JsonSerializable, asSingleton {

    public static $params = [];

    public function offsetSet($offset, $value): void {
        if (is_null($offset)) {
            self::$params[] = $value;
        } else {
            self::$params[$offset] = $value;
        }
    }

    public function offsetExists($offset): bool {
        return isset(self::$params[$offset]);
    }

    public function offsetUnset($offset): void {
        unset(self::$params[$offset]);
    }

    public function offsetGet($offset): mixed {
        return self::$params[$offset] ?? null;
    }

    public function jsonSerialize(): mixed {
        return self::$params;
    }
}