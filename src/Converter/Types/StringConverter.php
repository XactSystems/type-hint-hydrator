<?php

declare(strict_types=1);

namespace Xact\TypeHintHydrator\Converter\Types;

class StringConverter implements TypeConverterInterface
{
    /**
     * @inheritDoc
     */
    public static function convert($value): string
    {
        return strval($value);
    }
}
