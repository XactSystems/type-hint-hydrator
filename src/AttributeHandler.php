<?php

declare(strict_types=1);

namespace Xact\TypeHintHydrator;

use Xact\TypeHintHydrator\Attribute\Exclude;
use Xact\TypeHintHydrator\Attribute\SkipFind;
use Xact\TypeHintHydrator\Attribute\SkipHydrate;
use Doctrine\ORM\Mapping\Driver\AttributeReader;
use Nette\Utils\Reflection;
use ReflectionClass;

class AttributeHandler
{
    protected AttributeReader $reader;

    public function __construct()
    {
        $this->reader = new AttributeReader();
    }

    public function loadMetadataForClass(ReflectionClass $class): ClassMetadata
    {
        $className = $class->name;
        $classMetadata = new ClassMetadata($class);

        foreach ($this->reader->getClassAttributes($class) as $attribute) {
            if ($attribute instanceof Exclude) {
                $classMetadata->exclude = true;
            }
        }

        foreach ($class->getProperties() as $property) {
            if ($property->class !== $className && !is_subclass_of($className, $property->class)) {
                continue;
            }

            $propertyMetadata = new PropertyMetadata($property->getName());
            $propertyAttributes = $this->reader->getPropertyAttributes($property);
            foreach ($propertyAttributes as $attribute) {
                if ($attribute instanceof Exclude) {
                    $propertyMetadata->exclude = true;
                } elseif ($attribute instanceof SkipFind) {
                    $propertyMetadata->skipFind = true;
                } elseif ($attribute instanceof SkipHydrate) {
                    $propertyMetadata->skipHydrate = true;
                }
            }
            $classMetadata->setPropertyMetadata($propertyMetadata);
            $classMetadata->setUseStatements(Reflection::getUseStatements($class));
        }

        return $classMetadata;
    }
}
