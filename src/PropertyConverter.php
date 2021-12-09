<?php

namespace Xact\TypeHintHydrator;

use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\MappingException;
use Doctrine\Persistence\Mapping\MappingException as PersistenceMappingException;
use Nette\Utils\Arrays;
use Nette\Utils\Strings;
use ReflectionClass;
use ReflectionProperty;

class PropertyConverter
{
    /**
     * @var \Doctrine\ORM\EntityManagerInterface
     */
    protected $em;

    /**
     * @var \ReflectionClass
     */
    protected $targetClass;

    /**
     * @var string
     */
    protected $propertyName;

    /**
     * @var bool
     */
    protected $hasTypeDeclaration;

    /**
     * @var bool
     */
    protected $hasDefaultValue;

    /**
     * @var bool
     */
    protected $isNullable;

    /**
     * @var bool
     */
    protected $isMixed;

    /**
     * @var bool
     */
    protected $isMixedArray;

    /**
     * @var string[]
     */
    protected $allowedTypes;

    /**
     * @var string[]
     */
    protected $allowedArrayTypes;

    /**
     * @var string[]
     */
    protected $convertibleTypes = [];

    protected $entityHydrator;

    public function __construct(ReflectionProperty $property, EntityManagerInterface $em, ReflectionClass $targetClass, TypeHintHydrator $entityHydrator, object $targetObject)
    {
        $this->em = $em;
        $this->targetProperty = $property;
        $this->targetClass = $targetClass;
        $this->targetObject = $targetObject;
        $this->entityHydrator = $entityHydrator;

        $matches = Strings::match($property->getDocComment(), '/@var ((?:(?:[\w?|\\\\<>])+(?:\[])?)+)/');

        $definition = is_array($matches) ? $matches[1] : '';

        $this->propertyName = $property->getName();
        $this->hasTypeDeclaration = $definition !== '';
        $this->hasDefaultValue = $property->isDefault();
        $this->isNullable = $this->resolveNullable($definition);
        $this->isMixed = $this->resolveIsMixed($definition);
        $this->isMixedArray = $this->resolveIsMixedArray($definition);
        $this->allowedTypes = $this->resolveAllowedTypes($definition);
        $this->allowedArrayTypes = $this->resolveAllowedArrayTypes($definition);

        $classMetadata = $entityHydrator->getClassMetadata();
        $this->propertyMetadata = $classMetadata->getPropertyMetadata($this->propertyName);
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
    protected function convertToNativeTypeOrEntity($value, string $type)
    {
        // Attempt to cast to native types or entities.
        do {
            $converter = $this->entityHydrator->getConverter();
            if ($converter->canConvert($type)) {
                return $converter->convert($type, $value);
            }

            // If it's a class, see if it's an entity, or instantiate it, and hydrate it
            if (class_exists($type)) {
                if (is_string($value)) {
                    // If it's an entity and an association exists for this property, attempt to load the entity
                    try {
                        $targetMeta = $this->em->getClassMetadata($this->targetClass->getName());
                        if (!$this->propertyMetadata->skipFind && $targetMeta->hasAssociation($this->propertyName)) {
                            $association = $targetMeta->getAssociationMapping($this->propertyName);
                            // Sanity check that the association target is what we've detected as the property type
                            if ($this->prefixClass($type) === $this->prefixClass($association['targetEntity'])) {
                                $this->em->getClassMetadata($type)->getSingleIdentifierFieldName();
                                return $this->em->getRepository($type)->find($value);
                            }
                        }
                    } catch (MappingException $e) {
                        // Ignore, the class is not an entity, has no association, or the identifier is composite
                    } catch (PersistenceMappingException $e) {
                    }
                }

                if (is_array($value)) {
                    try {
                        // If property type is an entity, and we have an array of values for it, try and load and hydrate that entity
                        $idField = $this->em->getClassMetadata($type)->getSingleIdentifierFieldName();
                        if (array_key_exists($idField, $value)) {
                            /** @var object|null */
                            $entity = null;
                            $id = $value[$idField];
                            if (empty($id)) {
                                /**
                                 * Instantiate the entity and set the id property to null.
                                 * Most entities will be defined with a non nullable id property,
                                 * so if they are, say an int, they would get set to zero be default.
                                 */
                                $entity = new $type();
                                $entityReflection = new ReflectionClass($entity);
                                $idProperty = $entityReflection->getProperty($idField);
                                $idProperty->setAccessible(true);
                                $idProperty->setValue($entity, null);
                            } elseif (!$this->propertyMetadata->skipFind) {
                                $entity = $this->em->getRepository($type)->find($id);
                            }
                            if (!$this->propertyMetadata->skipHydrate) {
                                $this->entityHydrator->hydrateObject($value, $entity, false);
                            }
                            return $entity;
                        }
                    } catch (MappingException $e) {
                        // Ignore, the class is not an entity, does not have an identity or the identifier is composite
                    } catch (PersistenceMappingException $e) {
                    }

                    // Not an entity so try and create an instance of the class and hydrate it
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
    private function resolveAllowedTypes(string $definition): array
    {
        return $this->normaliseTypes(...explode('|', $definition));
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
