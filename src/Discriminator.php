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
        public array $legacyFieldnames = [],
    ) {}

    public function fill(array &$data): void
    {
        $data[$this->fieldname] = $this->value;
    }

    public function check(array &$data): bool
    {
        $fieldnames = [
            $this->fieldname,
            ...$this->legacyFieldnames
        ];

        foreach ($fieldnames as $fieldname) {
            if (array_key_exists($fieldname, $data)) {
                return $data[$fieldname] === $this->value;
            }
        }

        return false;
    }
}
