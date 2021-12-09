<?php

namespace Xact\TypeHintHydrator\Converter;

use InvalidArgumentException;
use Xact\TypeHintHydrator\Converter\Types\BoolConverter;
use Xact\TypeHintHydrator\Converter\Types\DateTimeConverter;
use Xact\TypeHintHydrator\Converter\Types\FloatConverter;
use Xact\TypeHintHydrator\Converter\Types\IntConverter;
use Xact\TypeHintHydrator\Converter\Types\StringConverter;
use Xact\TypeHintHydrator\Converter\Types\TypeConverterInterface;

class Converter implements ConverterInterface
{
    protected $convertibleTypes = [];

    public function __construct()
    {
        $this->convertibleTypes = [
            'int' => IntConverter::class,
            'bool' => BoolConverter::class,
            'float' => FloatConverter::class,
            'string' => StringConverter::class,
            'DateTime' => DateTimeConverter::class,
            '\DateTime' => DateTimeConverter::class,
        ];
    }

    public function canConvert(string $type): bool
    {
        return array_key_exists($type, $this->convertibleTypes);
    }

    /**
     * @inheritDoc
     */
    public function convert(string $type, $value)
    {
        if (!$this->canConvert($type)) {
            $validTypes = array_keys($this->convertibleTypes);
            throw new InvalidArgumentException("Cannot convert '{$type}', valid types are: {$validTypes}.");
        }

        $converterClass = $this->convertibleTypes[$type];
        $interface = TypeConverterInterface::class;
        if (!class_exists($converterClass)) {
            throw new InvalidArgumentException("The converter class '{$converterClass}' does not exist.");
        }
        if (!in_array($interface, class_implements($converterClass))) {
            throw new InvalidArgumentException("The converter class '{$converterClass}' does not implement the {$interface} interface.");
        }
        return $converterClass::convert($value);
    }
}
