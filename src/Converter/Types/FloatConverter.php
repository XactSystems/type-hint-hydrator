<?php

namespace Xact\TypeHintHydrator\Converter\Types;

class FloatConverter implements TypeConverterInterface
{
    /**
     * @inheritDoc
     */
    public static function convert($value): int
    {
        return floatval($value);
    }
}
