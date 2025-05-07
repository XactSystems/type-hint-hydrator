<?php

declare(strict_types=1);

namespace Xact\TypeHintHydrator\Converter\Types;

use ReflectionProperty;

interface TypeConverterInterface
{
    /**
     * Convert the specified.
     *
     * @param mixed $value The value to convert
     */
    public static function convert(mixed $value, ReflectionProperty $property, object $targetObject): mixed;
}
