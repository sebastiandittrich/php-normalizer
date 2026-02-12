<?php

namespace SebastianDittrich\Normalizer\Type;

interface Type
{
    public function humanName(): string;

    public function uniqueName(): string;
}
