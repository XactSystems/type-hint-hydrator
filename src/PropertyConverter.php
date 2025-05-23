<?php

declare(strict_types=1);

namespace Xact\TypeHintHydrator;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping\MappingException;
use Doctrine\Persistence\Mapping\MappingException as PersistenceMappingException;
use InvalidArgumentException;
use Nette\Utils\Arrays;
use Nette\Utils\Strings;
use ReflectionClass;
use ReflectionIntersectionType;
use ReflectionNamedType;
use ReflectionProperty;
use ReflectionUnionType;

class PropertyConverter
{
    protected ReflectionClass $targetClass;
    protected ReflectionProperty $targetProperty;
    protected TypeHintHydrator $entityHydrator;
    protected object $targetObject;
    protected string $propertyName;
    protected bool $hasTypeHint = false;
    protected bool $hasDefaultValue = false;
    protected bool $isNullable = false;
    protected bool $isMixed = false;
    protected bool $isMixedArray = false;
    protected bool $isIterable = false;
    protected ?PropertyMetadata $propertyMetadata = null;

    /** @var string[] */
    protected array $allowedTypes;

    /** @var string[] */
    protected array $allowedArrayTypes;

    /** @var string[] */
    protected array $convertibleTypes = [
        'integer' => 'int',
        'boolean' => 'bool',
    ];

    /** @var string[] */
    protected array $ignoredDeclaredTypes = ['self', 'parent', 'callable', 'object'];

    public function __construct(
        ReflectionProperty $property,
        ReflectionClass $targetClass,
        TypeHintHydrator $entityHydrator,
        object $targetObject
    ) {
        $this->targetProperty = $property;
        $this->targetClass = $targetClass;
        $this->entityHydrator = $entityHydrator;
        $this->targetObject = $targetObject;

        $this->configureTypeAttributes();
    }

    public function convert(mixed $value): mixed
    {
        /**
         * String types can receive the value as is as the Request parameters are all string values or arrays.
         */
        if (Arrays::contains($this->allowedTypes, 'string') && is_scalar($value)) {
            return $value;
        }

        /**
         * If the value is empty and this property is nullable, return null.
         * If it's not nullable and empty, throw an error. Empty strings are handled above.
         */
        if ($value === null || $value === '') {
            if ($this->isNullable) {
                return null;
            } else {
                throw new InvalidArgumentException(
                    "An error occurred hydrating an object of type {$this->targetClass->getName()}. /
                    Property {$this->propertyName} is not nullable and '{$value}' was given."
                );
            }
        }

        foreach ($this->allowedTypes as $type) {
            $convertedValue = null;

            /**
             * If the value is an array and the type ends in [], process the value as an array and try
             * to convert each element to type.
             */
            if (is_array($value) && str_ends_with($type, '[]')) {
                $propertyType = $this->getQualifiedClassName(Strings::before($type, '[]'));
                $convertedValue = [];
                foreach ($value as $subItem) {
                    $convertedValue[] = $this->convertToNativeTypeOrEntity($subItem, $propertyType);
                }
            }

            /**
             * If the value is an array and it's a typed Doctrine collection, process the value as an array
             * and try to convert each element to type, then add it to the collection.
             */
            if ($convertedValue === null && is_array($value)) {
                /**
                 * Skip Collection class without a subtype. This will be the >= 7.4 typed property definition.
                 * If this is the only allowed type, no @var, simply add the value to the collection.
                 */
                if (!str_contains($type, '<') && is_a($type, Collection::class, true)) {
                    if (count($this->allowedTypes) > 1) {
                        continue; // Wait for the @var definition with the <subtype>
                    }
                    // Simply add the values to the collection
                    $this->targetProperty->setAccessible(true);
                    $convertedValue = $this->targetProperty->getValue($this->targetObject);
                    foreach ($value as $itemValue) {
                        $convertedValue->add($itemValue);
                    }
                } else {
                    // Try and match Doctrine\Common\Collections\Collection<App\MyEntity> or Doctrine\Common\Collections\Collection<key, App\MyEntity>
                    $matches = Strings::match($type, '/(.*)(?:<)(?:\w+,\s*)?(.*)(?:>)/');
                    if ($matches !== null) {
                        $propertyType = $this->getQualifiedClassName($matches[1]);
                        $subType = $this->getQualifiedClassName($matches[2]);
                        if (is_a($propertyType, Collection::class, true)) {
                            $this->targetProperty->setAccessible(true);
                            /** @var Collection */
                            $convertedValue = $this->targetProperty->getValue($this->targetObject);
                            // Merge the hydrated children with the existing collection, deleting those that no longer exist.
                            $hydratedChildren = new ArrayCollection();
                            foreach ($value as $subItem) {
                                $child = $this->convertToNativeTypeOrEntity($subItem, $subType);
                                if (!$convertedValue->contains($child)) {
                                    // We only need to add it if it doesn't exist, convertToNativeTypeOrEntity above will hydrate any changes.
                                    $convertedValue->add($child);
                                }
                                $hydratedChildren->add($child);
                            }
                            foreach ($convertedValue as $oldChild) {
                                if (!$hydratedChildren->contains($oldChild)) {
                                    $convertedValue->removeElement($oldChild);
                                    $om = $this->entityHydrator->getManagerForClass($subType);
                                    if ($om !== null) {
                                        $om->remove($oldChild);
                                    }
                                }
                            }
                        }
                    }
                }
            }

            if ($convertedValue === null) {
                $convertedValue = $this->convertToNativeTypeOrEntity($value, $type);
            }

            if ($convertedValue !== null) {
                $value = $convertedValue;
                break;
            }
        }

        return $value;
    }

    private function convertToNativeTypeOrEntity(mixed $value, string $type): mixed
    {
        // Attempt to cast to native types or entities.
        do {
            $converter = $this->entityHydrator->getConverter();
            if ($converter->canConvert($type)) {
                return $converter->convert($type, $value, $this->targetProperty, $this->targetObject);
            }

            // If it's a class, see if it's an entity, or instantiate it, and hydrate it
            if (class_exists($this->prefixClass($type))) {
                $propertyClass = $this->prefixClass($type);

                /**
                 * The property is a string - if it's am entity try and find it
                 */
                if (is_scalar($value) && !$this->propertyMetadata->skipFind) {
                    try {
                        // If property type is an entity, try and load that entity
                        $om = $this->entityHydrator->getManagerForClass($propertyClass);
                        if ($om !== null) {
                            $om->getClassMetadata($propertyClass)->getSingleIdentifierFieldName();
                            $entity = $om->find($propertyClass, $value);
                            $om->initializeObject($entity); // Required for proxied entities.
                            return $entity;
                        }
                    } catch (MappingException | PersistenceMappingException $e) {
                        // Ignore, it's either not an entity or the key does not exist
                    }
                }

                /**
                 * The property is an array.
                 * If and we have the ID field, try and find and hydrate it.
                 * If we don't have the ID field, instantiate a new object and hydrate that.
                 */
                if (is_array($value) && !$this->propertyMetadata->skipFind) {
                    try {
                        // If property type is an entity, and we have the ID field in the values list, try and find and hydrate that entity
                        $om = $this->entityHydrator->getManagerForClass($propertyClass);
                        if ($om !== null) {
                            $idField = $om->getClassMetadata($type)->getSingleIdentifierFieldName();
                            if (array_key_exists($idField, $value) && !empty($value[$idField])) {
                                $entity = $om->find($propertyClass, $value[$idField]);
                                $om->initializeObject($entity); // Required for proxied entities.
                                $this->entityHydrator->hydrateEntity($value, $entity, false);
                                return $entity;
                            }
                        }
                    } catch (MappingException $e) {
                        // Ignore, the class is not an entity, does not have an identity or the identifier is composite
                    } catch (PersistenceMappingException $e) {
                    }

                    /**
                     * Instantiate the class and hydrate the object.
                     */
                    try {
                        $targetObject = new $type();
                        $this->entityHydrator->hydrateObject($value, $targetObject, false);
                        return $targetObject;
                    } catch (\Exception $e) {
                        /**
                         * The default action would be to hydrate the target with the value array,
                         * however we know it's a class so that isn't appropriate. Set it to null.
                         */
                         return null;
                    }
                }
            }
        } while (false);

        return $value;
    }

    protected function configureTypeAttributes(): void
    {
        $property = $this->targetProperty;
        $definition = '';
        // If we have a declared type for the property, use that as the primary type
        if ($property->hasType()) {
            $definition = $this->getPropertyTypeNames($property);
        }

        // If the @var annotation exists, append those types. For iterable types we use the @var definition for the array type.
        $matches = Strings::match((string)$property->getDocComment(), '/@var ((?:(?:[\w|\\\\]+(?:<(?:\w+,\s*)?[\w|\\\\]+>)?))(?:\[])?)/');
        $varTypes = is_array($matches) ? $matches[1] : '';
        if ($varTypes) {
            $this->hasTypeHint = true;

            // If we have an iterable declared type, use the type hint to suggest the array type
            if ($this->resolveIsIterable($definition)) {
                $definition = $varTypes;
            } else {
                // Otherwise the declared type takes precedence
                $definition .= ($definition ? '|' : '') . $varTypes;
            }
        }

        $this->propertyName = $property->getName();
        $this->isMixed = $this->resolveIsMixed($definition);
        $this->isMixedArray = $this->resolveIsMixedArray($definition);
        $this->isNullable = $this->resolveNullable($definition);
        $this->isIterable = $this->resolveIsIterable($definition);
        $this->allowedTypes = $this->normaliseTypes(...explode('|', $definition));
        $this->allowedArrayTypes = $this->resolveAllowedArrayTypes($definition);
        if (PHP_MAJOR_VERSION >= 8) {
            $this->hasDefaultValue = $property->hasDefaultValue();
        }

        $classMetadata = $this->entityHydrator->getClassMetadata($this->targetClass->getName());
        $this->propertyMetadata = $classMetadata->getPropertyMetadata($this->propertyName);
    }

    private function getPropertyTypeNames(ReflectionProperty $property): string
    {
        $reflectionType = $property->getType();
        switch (get_class($reflectionType)) {
            case ReflectionNamedType::class:
                return $this->resolveTypedProperty($reflectionType);
            case ReflectionUnionType::class:
            case ReflectionIntersectionType::class:
                $types = '';
                foreach ($reflectionType->getTypes() as $type) {
                    $type .= ($types ? '|' : '') . $this->resolveTypedProperty($type);
                }
                return $types;
        }

        return '';
    }

    private function resolveTypedProperty(ReflectionNamedType $type): string
    {
        $resolvedType = '';
        $typeName = $type->getName();
        if (!in_array($typeName, $this->ignoredDeclaredTypes)) {
            $typeName = $this->getQualifiedClassName($typeName);
            $resolvedType = $typeName . ($type->allowsNull() ? '|null' : '');
        }

        return $resolvedType;
    }

    private function resolveNullable(string $definition): bool
    {
        if (! $definition) {
            return true;
        }

        if (str_contains($definition, 'mixed') || str_contains($definition, 'null') || str_contains($definition, '?')) {
            return true;
        }

        return false;
    }

    private function resolveIsIterable(string $definition): bool
    {
        return (
            str_contains($definition, 'array')
            || str_contains($definition, 'iterable')
            || str_ends_with($definition, '[]')
            || str_contains($definition, '<') && str_ends_with($definition, '>')
            || (class_exists($definition) || interface_exists($definition)) && array_key_exists(\Traversable::class, class_implements($definition))
        );
    }

    private function resolveIsMixed(string $definition): bool
    {
        return str_contains($definition, 'mixed');
    }

    private function resolveIsMixedArray(string $definition): bool
    {
        $types = $this->normaliseTypes(...explode('|', $definition));

        foreach ($types as $type) {
            if (in_array($type, ['iterable', 'array'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return string[]
     */
    private function resolveAllowedArrayTypes(string $definition): array
    {
        return $this->normaliseTypes(...array_map(
            static function (string $type): ?string {
                if (! $type) {
                    return null;
                }

                if (strpos($type, '[]') !== false) {
                    return str_replace('[]', '', $type);
                }

                if (strpos($type, 'iterable<') !== false) {
                    return str_replace(['iterable<', '>'], ['', ''], $type);
                }

                if (strpos($type, 'array<') !== false) {
                    return str_replace(['array<', '>'], ['', ''], $type);
                }

                return null;
            },
            explode('|', $definition)
        ));
    }

    /**
     * @return string[]
     */
    private function normaliseTypes(?string ...$types): array
    {
        return array_filter(array_map(
            function (?string $type) {
                return $this->convertibleTypes[$type] ?? $type;
            },
            $types
        ));
    }

    private function prefixClass(string $className): string
    {
        return (str_starts_with($className, '\\')) ? $className : '\\' . $className;
    }

    private function getQualifiedClassName(string $propertyType): string
    {
        $metadata = $this->entityHydrator->getClassMetadata($this->targetClass->getName());
        if (($qualifiedClass = $metadata->getQualifiedClassName($propertyType)) !== null) {
            return $this->prefixClass($qualifiedClass);
        }
        return $propertyType;
    }
}
