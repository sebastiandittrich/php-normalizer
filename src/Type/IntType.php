<?php

namespace SebastianDittrich\Normalizer\Type;

class IntType implements Type
{
    public function humanName(): string
    {
        return 'int';
    }

    public function uniqueName(): string
    {
        return 'int';
    }
}
