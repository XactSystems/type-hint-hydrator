<?php

declare(strict_types=1);

namespace Xact\TypeHintHydrator\Converter\Types;

class IntConverter implements TypeConverterInterface
{
    /**
     * @inheritDoc
     */
    public static function convert($value): int
    {
        return intval($value);
    }
}
