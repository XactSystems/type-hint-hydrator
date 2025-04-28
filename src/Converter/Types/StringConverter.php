<?php

declare(strict_types=1);

namespace Xact\TypeHintHydrator\Converter\Types;

use ReflectionProperty;

class StringConverter implements TypeConverterInterface
{
    /**
     * @inheritDoc
     */
     // phpcs:ignore SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter
    public static function convert($value, ReflectionProperty $property, object $targetObject): string
    {
        return strval($value);
    }
}
