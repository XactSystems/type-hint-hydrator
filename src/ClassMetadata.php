<?php

declare(strict_types=1);

namespace Xact\TypeHintHydrator;

class ClassMetadata
{
    /**
     * @var string
     */
    public $name;

    /**
     * @var bool
     */
    public $exclude = false;

    /**
     * @var PropertyMetadata[]
     */
    protected $propertiesMetadata = [];

    public function __construct(string $name)
    {
        $this->name = $name;
    }

    public function getPropertyMetadata(string $name): ?PropertyMetadata
    {
        return isset($this->propertiesMetadata[$name])
            ? $this->propertiesMetadata[$name]
            : null;
    }

    public function setPropertyMetadata(PropertyMetadata $propertyMetadata): void
    {
        $this->propertiesMetadata[$propertyMetadata->name] = $propertyMetadata;
    }
}
