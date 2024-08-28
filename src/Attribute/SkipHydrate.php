<?php

declare(strict_types=1);

namespace Xact\TypeHintHydrator\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
final class SkipHydrate
{
}
