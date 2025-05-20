<?php

declare(strict_types=1);

namespace Xact\TypeHintHydrator;

use Laminas\Hydrator\Strategy\StrategyInterface;
use ReflectionClass;
use ReflectionProperty;

class PropertyTypeHintStrategy implements StrategyInterface
{
    protected PropertyConverter $Converter;

    public function __construct(ReflectionProperty $property, ReflectionClass $targetClass, TypeHintHydrator $hydrator, object $targetObject)
    {
        $this->Converter = new PropertyConverter($property, $targetClass, $hydrator, $targetObject);
    }

    /**
     * Converts the given value so that it can be extracted by the hydrator.
     *
     * @param  mixed       $value The original value.
     * @param  null|object $object (optional) The original object for context.
     * @return mixed       Returns the value that should be extracted.
     */
     // phpcs:ignore SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter
    public function extract(mixed $value, ?object $object = null): mixed
    {
        return $value;
    }

    /**
     * Converts the given value so that it can be hydrated by the hydrator.
     *
     * @param  mixed      $value The original value.
     * @param  null|mixed[] $data (optional) The original data for context.
     * @return mixed      Returns the value that should be hydrated.
     */
     // phpcs:ignore SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter
    public function hydrate(mixed $value, ?array $data = null): mixed
    {
        return $this->Converter->convert($value);
    }
}
