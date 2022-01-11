<?php

declare(strict_types=1);

namespace Xact\TypeHintHydrator\Converter\Types;

class FloatConverter implements TypeConverterInterface
{
    /**
     * @inheritDoc
     */
    public static function convert($value): float
    {
        return floatval($value);
    }
}
