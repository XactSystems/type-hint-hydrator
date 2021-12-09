<?php

namespace Xact\TypeHintHydrator\Converter\Types;

class StringConverter implements TypeConverterInterface
{
    /**
     * @inheritDoc
     */
    public static function convert($value): bool
    {
        return strval($value);
    }
}
