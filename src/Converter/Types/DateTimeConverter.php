<?php

declare(strict_types=1);

namespace Xact\TypeHintHydrator\Converter\Types;

use DateTime;

class DateTimeConverter implements TypeConverterInterface
{
    /**
     * @inheritDoc
     */
    public static function convert($value): DateTime
    {
        return ($value instanceof \DateTime ? $value : new \DateTime($value));
    }
}
