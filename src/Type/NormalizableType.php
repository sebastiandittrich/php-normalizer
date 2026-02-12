<?php

namespace SebastianDittrich\Normalizer\Type;

interface NormalizableType
{
    public function normalized(): Type;
}
