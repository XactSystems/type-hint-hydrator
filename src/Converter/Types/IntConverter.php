<?php

declare(strict_types=1);

namespace Xact\TypeHintHydrator\Converter\Types;

use ReflectionProperty;

class IntConverter implements TypeConverterInterface
{
    /**
     * @inheritDoc
     */
     // phpcs:ignore SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter
    public static function convert($value, ReflectionProperty $property, object $targetObject): int
    {
        return intval($value);
    }
}
