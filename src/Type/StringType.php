<?php

namespace SebastianDittrich\Normalizer\Type;

class StringType implements Type
{
    public function humanName(): string
    {
        return 'string';
    }

    public function uniqueName(): string
    {
        return 'string';
    }
}
