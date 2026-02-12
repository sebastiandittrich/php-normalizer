<?php

namespace SebastianDittrich\Normalizer;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class Discriminator
{
    use AttributeGetter;

    public function __construct(
        public string $value,
        public string $fieldname = 'type',
    ) {}

    public function fill(array &$data): void
    {
        $data[$this->fieldname] = $this->value;
    }

    public function check(array &$data): bool
    {
        if (! array_key_exists($this->fieldname, $data)) {
            return false;
        }

        return $data[$this->fieldname] === $this->value;
    }
}
