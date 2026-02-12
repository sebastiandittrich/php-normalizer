<?php

namespace SebastianDittrich\Normalizer;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class InterfaceMap
{
    /**
     * @param  array<string, class-string>  $implementations
     */
    public function __construct(
        public array $implementations,
    ) {}
}
