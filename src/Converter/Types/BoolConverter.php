<?php

declare(strict_types=1);

namespace Xact\TypeHintHydrator\Converter\Types;

use ReflectionProperty;

class BoolConverter implements TypeConverterInterface
{
    /**
     * @inheritDoc
     */
     // phpcs:ignore SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter
    public static function convert($value, ReflectionProperty $property, object $targetObject): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
}
