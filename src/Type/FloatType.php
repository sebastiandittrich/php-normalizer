<?php

namespace SebastianDittrich\Normalizer\Type;

class FloatType implements Type
{
    public function humanName(): string
    {
        return 'float';
    }

    public function uniqueName(): string
    {
        return 'float';
    }
}
