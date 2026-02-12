<?php

namespace SebastianDittrich\Normalizer;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class Version
{
    use AttributeGetter;

    public function __construct(
        public int $version,
        public string $fieldname = '__version',
    ) {}

    public function fill(array &$data): void
    {
        $data[$this->fieldname] = $this->version;
    }
}
