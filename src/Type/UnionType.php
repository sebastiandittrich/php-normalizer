<?php

namespace SebastianDittrich\Normalizer\Type;

class UnionType implements NormalizableType, Type
{
    /**
     * @param  Type[]  $types
     */
    public function __construct(
        public array $types,
    ) {}

    public function humanName(): string
    {
        return implode('|', array_map(fn(Type $type) => $type->humanName(), $this->types));
    }

    public function uniqueName(): string
    {
        return implode('|', array_map(fn(Type $type) => $type->uniqueName(), $this->types));
    }

    public function allows(string $typeClass): bool
    {
        return count($this->getMatchingTypes($typeClass)) > 0;
    }

    public function getMatchingTypes(string $typeClass): array
    {
        return array_filter($this->types, fn(Type $type) => is_a($type, $typeClass));
    }

    public function without(string $typeClass): self
    {
        return new self(array_filter($this->types, fn(Type $type) => ! is_a($type, $typeClass)));
    }

    public function flattened(): self
    {
        $types = array_values(
            array_unique(
                array_merge(
                    ...array_map(
                        fn(Type $type) => $type instanceof UnionType ? $type->flattened()->types : [$type],
                        $this->types
                    )
                )
            )
        );

        return new self($types);
    }

    public function normalized(): Type
    {
        $flat = $this->flattened();

        $flat = new self(array_map(
            fn(Type $type) => $type instanceof NormalizableType ? $type->normalized() : $type,
            $flat->types
        ));

        return match (count($flat->types)) {
            1 => $flat->types[0],
            default => $flat,
        };
    }

    public function nullableType(): ?Type
    {
        $types = $this->types;
        if (count($types) != 2) {
            return null;
        }
        $nullType = array_find($types, fn(Type $type) => $type instanceof NullType);
        if (! $nullType) {
            return null;
        }

        return array_find($types, fn(Type $type) => ! ($type instanceof NullType));
    }
}
