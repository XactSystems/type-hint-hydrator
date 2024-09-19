<?php

declare(strict_types=1);

namespace Xact\TypeHintHydrator;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use JMS\Serializer\SerializerInterface;
use Nette\Utils\Strings;
use ReflectionClass;
use ReflectionProperty;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\GroupSequence;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Xact\TypeHintHydrator\Converter\Converter;
use Xact\TypeHintHydrator\Converter\ConverterInterface;

class TypeHintHydrator
{
    protected const JSON_FORMAT = 'json';

    protected ValidatorInterface $validator;
    protected ManagerRegistry $doctrineRegistry;
    protected SerializerInterface $serializer;
    protected ?ClassMetadata $classMetadata = null;
    protected ConstraintViolationListInterface $errors;
    protected ?object $currentTarget;
    protected ?ReflectionClass $reflectionTarget;
    protected ConverterInterface $typeConverter;
    /** @var array<string, ClassMetadata> */
    protected array $metadataCache = [];

    public function __construct(ValidatorInterface $validator, ManagerRegistry $doctrineRegistry, SerializerInterface $serializer)
    {
        $this->validator = $validator;
        $this->doctrineRegistry = $doctrineRegistry;
        $this->serializer = $serializer;
        $this->typeConverter = new Converter();
        $this->errors = new ConstraintViolationList();
    }

    public function setConverter(ConverterInterface $converter): self
    {
        $this->typeConverter = $converter;
        return $this;
    }

    public function getConverter(): ConverterInterface
    {
        return $this->typeConverter;
    }

    /**
     * @param mixed[] $values
     * @param Constraint|Constraint[] $constraints  The constraint(s) to validate against
     * @param string|GroupSequence|(string|GroupSequence)[]|null $groups  The validation groups to validate. If none is given, "Default" is assumed
     * @throws \Laminas\Hydrator\Exception\InvalidArgumentException
     */
    public function hydrateObject(
        array $values,
        object $target,
        bool $validate = true,
        Constraint|array $constraints = null,
        string|GroupSequence|array|null $groups = null
    ): object {
        $this->currentTarget = $target;
        $this->reflectionTarget = $this->getReflectionTarget($target);
        $this->classMetadata = (new AttributeHandler())->loadMetadataForClass($this->reflectionTarget);
        $this->metadataCache[$this->reflectionTarget->getName()] = $this->classMetadata;

        if ($this->classMetadata->exclude) {
            return $target;
        }

        /**
         * Build a list of strategies for each property in the target object.
         */
        $strategies = [];
        $properties = $this->reflectionTarget->getProperties();
        foreach ($properties as $property) {
            $propertyName = $property->getName();
            $propertyMetadata = $this->classMetadata->getPropertyMetadata($propertyName);
            if ($propertyMetadata === null || !$propertyMetadata->exclude) {
                $strategies[$propertyName] = new PropertyTypeHintStrategy($property, $this->reflectionTarget, $this, $target);
            }
        }

        $hydrator = new ReflectionHydrator();
        foreach ($strategies as $key => $strategy) {
            $hydrator->addStrategy($key, $strategy);
        }

        $hydratedObject = $hydrator->hydrate($values, $target, true);

        if ($validate) {
            $this->errors = $this->validator->validate($hydratedObject, $constraints, $groups);
        }

        return $hydratedObject;
    }

    /**
     * @param Constraint|Constraint[]|null $constraints  The constraint(s) to validate against
     * @param string|GroupSequence|(string|GroupSequence)[]|null $groups  The validation groups to validate. If none is given, "Default" is assumed
     */
    public function handleRequest(
        Request $request,
        object $target,
        bool $validate = true,
        Constraint|array|null $constraints = null,
        string|GroupSequence|array|null $groups = null
    ): object {
        return $this->hydrateObject($request->request->all(), $target, $validate, $constraints, $groups);
    }

    public function isValid(): bool
    {
        return (count($this->errors) === 0);
    }

    public function getErrors(): ConstraintViolationListInterface
    {
        return $this->errors;
    }

    public function getJsonErrors(): string
    {
        return $this->serializer->serialize($this->errors, self::JSON_FORMAT);
    }

    public function getClassMetadata(string $className): ?ClassMetadata
    {
        $this->addMetadataCacheClass($className);
        return $this->metadataCache[$className] ?? null;
    }

    public function getManagerForClass(string $className): ?EntityManagerInterface
    {
        // Doctrine will not find a match if the class name is prefixed with a '\'. Oh the joy of consistency!
        if (str_starts_with($className, '\\')) {
            $className = Strings::substring($className, 1);
        }

        return $this->doctrineRegistry->getManagerForClass($className);
    }

    /**
     * @return mixed
     * @throws \ReflectionException
     */
    public function getOriginalValue(string $propertyName): ?ReflectionProperty
    {
        if ($this->currentTarget && $this->reflectionTarget && $this->reflectionTarget->hasProperty($propertyName)) {
            $property = $this->reflectionTarget->getProperty($propertyName);
            $property->setAccessible(true);
            return $property->getValue($this->currentTarget);
        }

        return null;
    }

    protected function getReflectionTarget(object $object): ReflectionClass
    {
        // return a ReflectionClass object for the entity. If $target is a proxy, return it for the base entity.
        $proxyOrEntityClassName = get_class($object);
        $entityClassName = (
            ($manager = $this->doctrineRegistry->getManagerForClass($proxyOrEntityClassName))
                ? $manager->getClassMetadata($proxyOrEntityClassName)->getName()
                : $proxyOrEntityClassName
        );
        return new ReflectionClass($proxyOrEntityClassName === $entityClassName ? $object : get_parent_class($object));
    }

    protected function addMetadataCacheClass(string $className): void
    {
        if (class_exists($className) && !array_key_exists($className, $this->metadataCache)) {
            $this->metadataCache[$className] = (new AttributeHandler())->loadMetadataForClass(new ReflectionClass($className));
        }
    }
}
