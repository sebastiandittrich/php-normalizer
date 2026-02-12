<?php

namespace SebastianDittrich\Normalizer\Type;

class BoolType implements Type
{
    public function humanName(): string
    {
        return 'bool';
    }

    public function uniqueName(): string
    {
        return 'bool';
    }
}
