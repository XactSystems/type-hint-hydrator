<?php

declare(strict_types=1);

namespace Xact\TypeHintHydrator;

use Doctrine\Laminas\Hydrator\DoctrineObject as DoctrineHydrator;
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
        $this->reflectionTarget = new ReflectionClass($target);
        $this->classMetadata = (new AttributeHandler())->loadMetadataForClass($this->reflectionTarget);
        $this->metadataCache[$this->reflectionTarget->getName()] = $this->classMetadata;

        if ($this->classMetadata->exclude) {
            return $target;
        }

        // If the target object is a Doctrine entity, use the Doctrine hydrator. Otherwise use the Reflection hydrator
        $properties = $this->reflectionTarget->getProperties();
        $entityManager = $this->getManagerForClass($this->reflectionTarget->getName());
        $hydrator = (
            $entityManager instanceof EntityManagerInterface ?
                new DoctrineHydrator($entityManager, $this->reflectionTarget->getName()) :
                new ReflectionHydrator()
        );
        foreach ($properties as $property) {
            $propertyName = $property->getName();
            $propertyMetadata = $this->classMetadata->getPropertyMetadata($propertyName);
            if ($propertyMetadata === null || !$propertyMetadata->exclude) {
                $strategy = new PropertyTypeHintStrategy($property, $this->reflectionTarget, $this, $target);
                $hydrator->addStrategy($propertyName, $strategy);
            }
        }
        $hydratedObject = $hydrator->hydrate($values, $target);

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

    /** @phpstan-impure */
    public function isValid(): bool
    {
        return (count($this->errors) === 0);
    }

    /** @phpstan-impure */
    public function getErrors(): ConstraintViolationListInterface
    {
        return $this->errors;
    }

    /** @phpstan-impure */
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

    protected function addMetadataCacheClass(string $className): void
    {
        if (class_exists($className) && !array_key_exists($className, $this->metadataCache)) {
            $this->metadataCache[$className] = (new AttributeHandler())->loadMetadataForClass(new ReflectionClass($className));
        }
    }
}
