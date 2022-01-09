<?php

namespace Xact\TypeHintHydrator;

use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\MappingException;
use Doctrine\Persistence\Mapping\MappingException as PersistenceMappingException;
use Nette\Utils\Arrays;
use Nette\Utils\Strings;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionProperty;
use ReflectionUnionType;

class PropertyConverter
{
    protected EntityManagerInterface $em;
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

    /**
     * @var string[]
     */
    protected array $allowedTypes;

    /**
     * @var string[]
     */
    protected array $allowedArrayTypes;

    /**
     * @var string[]
     */
    protected array $convertibleTypes = [];

    protected array $ignoredDeclaredTypes = ['self', 'parent', 'callable', 'object'];

    public function __construct(ReflectionProperty $property, EntityManagerInterface $em, ReflectionClass $targetClass, TypeHintHydrator $entityHydrator, object $targetObject)
    {
        $this->em = $em;
        $this->targetProperty = $property;
        $this->targetClass = $targetClass;
        $this->entityHydrator = $entityHydrator;
        $this->targetObject = $targetObject;

        $this->configureTypeAttributes();
    }

    /**
     * @param mixed $value
     * @return mixed
     */
    public function convert($value)
    {
        /**
         * String types can receive the value as is as the
         * Request parameters are all string values or arrays.
         */
        if (Arrays::contains($this->allowedTypes, 'string') && is_string($value)) {
            return $value;
        }

        /**
         * If the value is empty and this property is
         * nullable, return null.
         */
        if (($value === null || $value === '') && $this->isNullable) {
            return null;
        }

        foreach ($this->allowedTypes as $type) {
            $convertedValue = null;

            /**
            * If the value is an array and the type ends in [],
            * process the value as an array and try to convert
            * each element to type.
            */
            if (is_array($value) && Strings::endsWith($type, '[]')) {
                $realType = Strings::before($type, '[]');
                $convertedValue = [];
                foreach ($value as $subItem) {
                    $convertedValue[] = $this->convertToNativeTypeOrEntity($subItem, $realType);
                }
            }

            /**
            * If the value is an array and it's a typed Doctrine
            * collection, process the value as an array and try
            * to convert each element to type, then add it to
            * the collection.
            */
            if ($convertedValue === null && is_array($value) && Strings::endsWith($type, '>')) {
                $this->targetProperty->setAccessible(true);
                $targetProperty = $this->targetProperty->getValue($this->targetObject);
                if (is_subclass_of($targetProperty, Collection::class)) {
                    $matches = Strings::match($type, '/(?:<)(\\\[\w\\\]*)(?:>)/');
                    if ($matches !== null) {
                        $realType = $matches[1];
                        $convertedValue = $targetProperty;
                        foreach ($value as $subItem) {
                            $convertedValue->add($this->convertToNativeTypeOrEntity($subItem, $realType));
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

    /**
     * @param mixed $value
     * @return mixed
     */
    private function convertToNativeTypeOrEntity($value, string $type)
    {
        // Attempt to cast to native types or entities.
        do {
            $converter = $this->entityHydrator->getConverter();
            if ($converter->canConvert($type)) {
                return $converter->convert($type, $value);
            }

            // If it's a class, see if it's an entity, or instantiate it, and hydrate it
            if (class_exists($this->prefixClass($type))) {
                $propertyClass = $this->prefixClass($type);
                if (is_string($value) && !$this->propertyMetadata->skipFind) {
                    try {
                        // If property type is an entity, and we have an array of values for it, try and load and hydrate that entity
                        $this->em->getClassMetadata($propertyClass)->getSingleIdentifierFieldName();
                        return $this->em->getRepository($propertyClass)->find($value);
                    } catch (MappingException | PersistenceMappingException $e) {
                        // Ignore, it's either not an entity or the key does not exist
                    }
                }

                // If we have an array of values, instantiate the class and fill it
                if (is_array($value)) {
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
        $matches = Strings::match($property->getDocComment(), '/@var ((?:(?:[\w?|\\\\<>])+(?:\[])?)+)/');
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

        $classMetadata = $this->entityHydrator->getClassMetadata();
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
            $resolvedType = $typeName . ($type->allowsNull() ? '|null' : '');
        }

        return $resolvedType;
    }

    private function resolveNullable(string $definition): bool
    {
        if (! $definition) {
            return true;
        }

        if (Strings::contains($definition, 'mixed') || Strings::contains($definition, 'null') || Strings::contains($definition, '?')) {
            return true;
        }

        return false;
    }

    private function resolveIsIterable(string $definition): bool
    {
        return (
            Strings::contains($definition, 'array')
            || Strings::contains($definition, 'iterable')
            || Strings::endsWith($definition, '[]')
        );
    }

    private function resolveIsMixed(string $definition): bool
    {
        return Strings::contains($definition, 'mixed');
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
            function (string $type): ?string {
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
                return self::$convertibleTypes[$type] ?? $type;
            },
            $types
        ));
    }

    private function prefixClass(string $className): string
    {
        return (Strings::startsWith($className, '\\')) ? $className : '\\' . $className;
    }
}
