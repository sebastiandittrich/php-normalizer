<?php

namespace SebastianDittrich\Normalizer\Type;

use ReflectionEnum;

class EnumType implements Type
{
    public function __construct(
        public string $enumName,
    ) {}

    public function humanName(): string
    {
        return $this->enumName;
    }

    public function uniqueName(): string
    {
        return $this->enumName;
    }

    public function reflect(): ReflectionEnum
    {
        return new ReflectionEnum($this->enumName);
    }
}
