<?php

declare(strict_types=1);

namespace Xact\TypeHintHydrator;

class PropertyMetadata
{
    public string $name;
    public bool $exclude = false;
    public bool $skipFind = false;
    public bool $skipHydrate = false;

    public function __construct(string $propertyName)
    {
        $this->name = $propertyName;
    }
}
