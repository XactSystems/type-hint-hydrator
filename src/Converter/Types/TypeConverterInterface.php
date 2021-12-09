<?php

namespace Xact\TypeHintHydrator\Converter\Types;

interface TypeConverterInterface
{
    /**
     * Convert the specified.
     *
     * @param mixed $value The value to convert
     * @return mixed
     */
    public static function convert($value);
}
