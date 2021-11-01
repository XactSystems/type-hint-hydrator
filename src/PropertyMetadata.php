<?php

declare(strict_types=1);

namespace Xact\TypeHintHydrator;

class PropertyMetadata
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
     * @var bool
     */
    public $skipFind = false;

    /**
     * @var bool
     */
    public $skipHydrate = false;

    public function __construct(string $propertyName)
    {
        $this->name = $propertyName;
    }
}
