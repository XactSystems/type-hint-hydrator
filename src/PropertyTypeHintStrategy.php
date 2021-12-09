<?php

namespace Xact\TypeHintHydrator;

use Doctrine\ORM\EntityManagerInterface;
use Laminas\Hydrator\Strategy\StrategyInterface;
use ReflectionClass;
use ReflectionProperty;

class PropertyTypeHintStrategy implements StrategyInterface
{
    /**
     * @var \Xact\TypeHintHydrator\PropertyConverter
     */
    protected $Converter;

    public function __construct(ReflectionProperty $property, EntityManagerInterface $em, ReflectionClass $targetClass, TypeHintHydrator $hydrator, object $targetObject)
    {
        $this->Converter = new PropertyConverter($property, $em, $targetClass, $hydrator, $targetObject);
    }

    /**
     * Converts the given value so that it can be extracted by the hydrator.
     *
     * @param  mixed       $value The original value.
     * @param  null|object $object (optional) The original object for context.
     * @return mixed       Returns the value that should be extracted.
     */
     // phpcs:ignore SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter
    public function extract($value, ?object $object = null)
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
    public function hydrate($value, ?array $data = null)
    {
        return $this->Converter->convert($value);
    }
}
