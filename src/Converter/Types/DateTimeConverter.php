<?php

declare(strict_types=1);

namespace Xact\TypeHintHydrator\Converter\Types;

use DateTime;
use ReflectionProperty;

class DateTimeConverter implements TypeConverterInterface
{
    /**
     * @inheritDoc
     */
    public static function convert($value, ReflectionProperty $property, object $targetObject): DateTime
    {
        $dateTimeObject = ($value instanceof \DateTime ? $value : new \DateTime($value));
        $property->setAccessible(true);
        // If the date-time value hasn't changed, keep the original DateTime object.
        if (($currentDateTimeObject = $property->getValue($targetObject)) instanceof \DateTime) {
            if ($currentDateTimeObject == $dateTimeObject) {
                $dateTimeObject = $currentDateTimeObject;
            }
        }
        return $dateTimeObject;
    }
}
