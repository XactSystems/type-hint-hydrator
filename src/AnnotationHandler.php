<?php

declare(strict_types=1);

namespace Xact\TypeHintHydrator;

use Xact\TypeHintHydrator\Annotation\Exclude;
use Xact\TypeHintHydrator\Annotation\SkipFind;
use Xact\TypeHintHydrator\Annotation\SkipHydrate;
use Doctrine\Common\Annotations\AnnotationReader;

class AnnotationHandler
{
    /**
     * @var AnnotationReader
     */
    protected $reader;

    public function __construct()
    {
        $this->reader = new AnnotationReader();
    }

    public function loadMetadataForClass(\ReflectionClass $class): ClassMetadata
    {
        $name = $class->name;
        $classMetadata = new ClassMetadata($name);

        foreach ($this->reader->getClassAnnotations($class) as $annotation) {
            if ($annotation instanceof Exclude) {
                $classMetadata->exclude = true;
            }
        }

        foreach ($class->getProperties() as $property) {
            if ($property->class !== $name || (isset($property->info) && $property->info['class'] !== $name)) {
                continue;
            }

            $propertyMetadata = new PropertyMetadata($property->getName());
            $propertyAnnotations = $this->reader->getPropertyAnnotations($property);
            foreach ($propertyAnnotations as $annotation) {
                if ($annotation instanceof Exclude) {
                    $propertyMetadata->exclude = true;
                } elseif ($annotation instanceof SkipFind) {
                    $propertyMetadata->skipFind = true;
                } elseif ($annotation instanceof SkipHydrate) {
                    $propertyMetadata->skipHydrate = true;
                }
            }
            $classMetadata->setPropertyMetadata($propertyMetadata);
        }

        return $classMetadata;
    }
}
