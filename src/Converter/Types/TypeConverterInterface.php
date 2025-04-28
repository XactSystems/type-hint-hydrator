<?php

namespace Xact\TypeHintHydrator\Converter\Types;

use ReflectionProperty;

interface TypeConverterInterface
{
    /**
     * Convert the specified.
     *
     * @param mixed $value The value to convert
     * @return mixed
     */
    public static function convert($value, ReflectionProperty $property, object $targetObject);
}
