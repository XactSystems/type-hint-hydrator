<?php

declare(strict_types=1);

namespace Xact\TypeHintHydrator;

use Xact\TypeHintHydrator\Annotation\Exclude;
use Xact\TypeHintHydrator\Annotation\SkipFind;
use Xact\TypeHintHydrator\Annotation\SkipHydrate;
use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Annotations\PhpParser;
use ReflectionClass;

class AnnotationHandler
{
    protected AnnotationReader $reader;
    protected PhpParser $phpParser;

    public function __construct()
    {
        $this->reader = new AnnotationReader();
        $this->phpParser = new PhpParser();
    }

    public function loadMetadataForClass(ReflectionClass $class): ClassMetadata
    {
        $className = $class->name;
        $classMetadata = new ClassMetadata($class);

        foreach ($this->reader->getClassAnnotations($class) as $annotation) {
            if ($annotation instanceof Exclude) {
                $classMetadata->exclude = true;
            }
        }

        foreach ($class->getProperties() as $property) {
            if (
                ($property->class !== $className && !is_subclass_of($className, $property->class)) ||
                (isset($property->info) && $property->info['class'] !== $className)
            ) {
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
            $classMetadata->setUseStatements($this->phpParser->parseUseStatements($class));
        }

        return $classMetadata;
    }
}
