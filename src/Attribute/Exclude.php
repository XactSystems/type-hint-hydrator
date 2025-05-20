<?php

declare(strict_types=1);

namespace Xact\TypeHintHydrator\Attribute;

use Attribute;

#[Attribute(flags: Attribute::TARGET_CLASS & Attribute::TARGET_PROPERTY)]
final class Exclude
{
}
