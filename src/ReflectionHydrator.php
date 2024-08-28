<?php

declare(strict_types=1);

namespace Xact\TypeHintHydrator;

use Laminas\Hydrator\AbstractHydrator;
use ReflectionClass;
use ReflectionProperty;

/**
 * TODO: Remove this class when the base hydrator issue 114 is resolved. https://github.com/laminas/laminas-hydrator/issues/114
 */
class ReflectionHydrator extends AbstractHydrator
{
    /**
     * Simple in-memory array cache of ReflectionProperties used.
     *
     * @var ReflectionProperty[][]
     */
    protected static array $reflectionProperties = [];

    /**
     * Extract values from an object
     *
     * {@inheritDoc}
     */
    public function extract(object $object, bool $includeParentProperties = false): array
    {
        $result = [];
        foreach (static::getReflectionProperties($object, $includeParentProperties) as $property) {
            $propertyName = $this->extractName($property->getName(), $object);
            if (! $this->getCompositeFilter()->filter($propertyName)) {
                continue;
            }

            $value                 = $property->getValue($object);
            $result[$propertyName] = $this->extractValue($propertyName, $value, $object);
        }

        return $result;
    }

    /**
     * Hydrate $object with the provided $data.
     *
     * @param array<string, mixed> $data
     * {@inheritDoc}
     */
    public function hydrate(array $data, object $object, bool $includeParentProperties = false): object
    {
        $reflectionProperties = static::getReflectionProperties($object, $includeParentProperties);
        foreach ($data as $key => $value) {
            $name = $this->hydrateName($key, $data);
            if (isset($reflectionProperties[$name])) {
                $reflectionProperties[$name]->setValue($object, $this->hydrateValue($name, $value, $data));
            }
        }
        return $object;
    }

    /**
     * Get a reflection properties for an object.
     * If $includeParentProperties is true, return return all parent properties as well.
     *
     * @return ReflectionProperty[]
     */
    protected static function getReflectionProperties(object $input, bool $includeParentProperties): array
    {
        $class = get_class($input);

        if (isset(static::$reflectionProperties[$class])) {
            return static::$reflectionProperties[$class];
        }

        static::$reflectionProperties[$class] = [];
        $reflectionClass = new ReflectionClass($class);

        do {
            foreach ($reflectionClass->getProperties() as $property) {
                $property->setAccessible(true);
                static::$reflectionProperties[$class][$property->getName()] = $property;
            }
        } while ($includeParentProperties === true && ($reflectionClass = $reflectionClass->getParentClass()) !== false);

        return static::$reflectionProperties[$class];
    }
}
