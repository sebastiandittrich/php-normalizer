<?php

namespace SebastianDittrich\Normalizer\Type;

class ClassType implements Type
{
    public function __construct(
        public string $className,
    ) {}

    public function humanName(): string
    {
        return $this->className;
    }

    public function uniqueName(): string
    {
        return $this->className;
    }

    public function reflect(): \ReflectionClass
    {
        return new \ReflectionClass($this->className);
    }
}
