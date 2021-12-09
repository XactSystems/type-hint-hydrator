<?php

namespace Xact\TypeHintHydrator\Converter\Types;

class BoolConverter implements TypeConverterInterface
{
    /**
     * @inheritDoc
     */
    public static function convert($value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
}
