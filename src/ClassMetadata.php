<?php

declare(strict_types=1);

namespace Xact\TypeHintHydrator;

use ReflectionClass;

class ClassMetadata
{
    public string $className;
    public string $nameSpace;
    public bool $exclude = false;
    /** @var array<string, string> a list with use statements in the form (Alias => FQN). */
    protected array $useStatements = [];
    /** @var PropertyMetadata[] */
    protected array $propertiesMetadata = [];

    public function __construct(ReflectionClass $class)
    {
        $this->className = $class->getName();
        $this->nameSpace = $class->getNamespaceName();
    }

    public function getPropertyMetadata(string $propertyName): ?PropertyMetadata
    {
        return isset($this->propertiesMetadata[$propertyName])
            ? $this->propertiesMetadata[$propertyName]
            : null;
    }

    public function setPropertyMetadata(PropertyMetadata $propertyMetadata): void
    {
        $this->propertiesMetadata[$propertyMetadata->name] = $propertyMetadata;
    }

    /**
     * @param array<string, string> $useStatements A list with use statements in the form (Alias => FQN).
     */
    public function setUseStatements(array $useStatements): void
    {
        $this->useStatements = $useStatements;
    }

    /**
     * @return array<string, string> A list with use statements in the form (Alias => FQN).
     */
    public function getUseStatements(): array
    {
        return $this->useStatements;
    }

    /**
     * Return the qualified class name from use statements if it exists, otherwise null.
     */
    public function getQualifiedClassName(string $class): ?string
    {
        $loweredClass = strtolower($class);
        if (array_key_exists($loweredClass, $this->useStatements)) {
            return $this->useStatements[$loweredClass];
        }
        if (array_search($class, $this->useStatements) !== false) {
            return $class;
        }
        $namespacedClass = "{$this->nameSpace}\\{$class}";
        if (class_exists($namespacedClass)) {
            return $namespacedClass;
        }
        return null;
    }
}
