<?php

namespace SebastianDittrich\Normalizer\Type;

class InterfaceType implements Type
{
    public function __construct(
        public string $interfaceName,
    ) {}

    public function humanName(): string
    {
        return $this->interfaceName;
    }

    public function uniqueName(): string
    {
        return $this->interfaceName;
    }
}
