<?php

namespace SebastianDittrich\Normalizer\Type;

class ArrayType implements NormalizableType, Type
{
    public function __construct(
        public Type $itemType,
    ) {}

    public function humanName(): string
    {
        return "{$this->itemType->humanName()}[]";
    }

    public function uniqueName(): string
    {
        return "{$this->itemType->uniqueName()}[]";
    }

    public function normalized(): ArrayType
    {
        return new ArrayType(
            $this->itemType instanceof NormalizableType ? $this->itemType->normalized() : $this->itemType
        );
    }
}
