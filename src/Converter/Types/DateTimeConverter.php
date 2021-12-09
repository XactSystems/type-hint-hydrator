<?php

namespace Xact\TypeHintHydrator\Converter\Types;

use DateTime;

class DateTimeConverter implements TypeConverterInterface
{
    /**
     * @inheritDoc
     */
    public static function convert($value): DateTime
    {
        return new \DateTime($value);
    }
}
