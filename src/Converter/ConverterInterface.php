<?php

declare(strict_types=1);

namespace Xact\TypeHintHydrator\Converter;

use ReflectionProperty;

interface ConverterInterface
{
    /**
     * Determine if the specified type can be converted by this Converter.
     */
    public function canConvert(string $type): bool;

    /**
     * Convert the specified value according to the required type.
     *
     * @param string $type The type to convert to, int, bool, float etc.
     */
    public function convert(string $type, mixed $value, ReflectionProperty $property, object $targetObject): mixed;
}
