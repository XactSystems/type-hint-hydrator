<?php

declare(strict_types=1);

namespace Xact\TypeHintHydrator;

use Doctrine\Common\Proxy\Proxy;
use Laminas\Hydrator\ReflectionHydrator as LaminasReflectionHydrator;
use ReflectionClass;
use ReflectionProperty;

class ReflectionHydrator extends LaminasReflectionHydrator
{
    /**
     * Extract values from an object
     * TODO: Remove this method when issue 113 is resolved: https://github.com/laminas/laminas-hydrator/issues/113
     *
     * {@inheritDoc}
     */
    public function extract(object $object): array
    {
        $result = [];
        foreach (static::getReflProperties($object) as $property) {
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
     * TODO: Remove this method when issue 113 is resolved: https://github.com/laminas/laminas-hydrator/issues/113
     *
     * {@inheritDoc}
     */
    public function hydrate(array $data, object $object)
    {
        $reflProperties = static::getReflProperties($object);
        foreach ($data as $key => $value) {
            $name = $this->hydrateName($key, $data);
            if (isset($reflProperties[$name])) {
                $reflProperties[$name]->setValue($object, $this->hydrateValue($name, $value, $data));
            }
        }
        return $object;
    }

    /**
     * Get a reflection properties for an object.
     * If that object is a Doctrine proxy, return the base entity class properties.
     *
     * @return ReflectionProperty[]
     */
    protected static function getReflProperties(object $input): array
    {
        $class = $input instanceof Proxy ? get_parent_class($input) : get_class($input);

        if (isset(static::$reflProperties[$class])) {
            return static::$reflProperties[$class];
        }

        static::$reflProperties[$class] = [];
        $reflClass                      = new ReflectionClass($class);
        $reflProperties                 = $reflClass->getProperties();

        foreach ($reflProperties as $property) {
            $property->setAccessible(true);
            static::$reflProperties[$class][$property->getName()] = $property;
        }

        return static::$reflProperties[$class];
    }
}
