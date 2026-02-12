<?php

namespace SebastianDittrich\Normalizer\Type;

class NullType implements Type
{
    public function humanName(): string
    {
        return 'null';
    }

    public function uniqueName(): string
    {
        return 'null';
    }
}
