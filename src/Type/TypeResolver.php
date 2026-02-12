<?php

namespace SebastianDittrich\Normalizer\Type;

use ReflectionNamedType;
use ReflectionParameter;
use ReflectionType;
use ReflectionUnionType;
use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use SebastianDittrich\Normalizer\Type\ArrayType;
use SebastianDittrich\Normalizer\Type\Type;
use SebastianDittrich\Normalizer\Type\StringType;
use SebastianDittrich\Normalizer\Type\IntType;
use SebastianDittrich\Normalizer\Type\BoolType;
use SebastianDittrich\Normalizer\Type\FloatType;
use SebastianDittrich\Normalizer\Type\UnionType;
use SebastianDittrich\Normalizer\Type\EnumType;
use SebastianDittrich\Normalizer\Type\InterfaceType;
use SebastianDittrich\Normalizer\Type\ClassType;
use SebastianDittrich\Normalizer\Type\NullType;
use SebastianDittrich\Normalizer\Type\NormalizableType;

class TypeResolver
{
    public function typeFromArrayParameter(ReflectionParameter $reflectionParameter): ArrayType
    {
        $types = (new PhpDocExtractor)->getTypes($reflectionParameter->getDeclaringClass()->getName(), $reflectionParameter->getName());
        if ($types === null) {
            throw new \Exception('Cannot denormalize data, missing type for parameter ' . $reflectionParameter->getName());
        }
        if (count($types) != 1) {
            throw new \Exception('Cannot denormalize data, array type invalid');
        }
        if (! ($arrayType = ($types[0] ?? null))) {
            throw new \Exception('Cannot denormalize data, array type invalid');
        }
        $valueTypes = $arrayType->getCollectionValueTypes();
        if (count($valueTypes) != 1) {
            throw new \Exception('Cannot denormalize data, array type invalid');
        }
        if (! ($valueType = ($valueTypes[0] ?? null))) {
            throw new \Exception('Cannot denormalize data, array type invalid');
        }

        return new ArrayType(
            $this->typeFromString($valueType->getClassName() ?? $valueType->getBuiltinType())
        );
    }

    public function typeFromString(string $typeName): Type
    {
        return match ($typeName) {
            'string' => new StringType,
            'int' => new IntType,
            'bool' => new BoolType,
            'float' => new FloatType,
            'null' => new NullType,
            default => match (true) {
                interface_exists($typeName) => new InterfaceType($typeName),
                enum_exists($typeName) => new EnumType($typeName),
                class_exists($typeName) => new ClassType($typeName),
                str_ends_with($typeName, '[]') => new ArrayType(
                    $this->typeFromString(substr($typeName, 0, -2))
                ),
                str_contains($typeName, '|') => new UnionType(
                    array_map(fn(string $type) => $this->typeFromString($type), explode('|', $typeName))
                ),
                default => throw new \Exception('Cannot denormalize data of type ' . $typeName),
            },
        };
    }

    public function typeFromReflectionType(ReflectionType $reflectionType): Type
    {
        return match (true) {
            $reflectionType instanceof ReflectionNamedType => match (true) {
                $reflectionType->isBuiltin() && $reflectionType->getName() === 'null' => new NullType,
                $reflectionType->allowsNull() => new UnionType([$this->typeFromString($reflectionType->getName()), new NullType]),
                default => $this->typeFromString($reflectionType->getName()),
            },
            $reflectionType instanceof ReflectionUnionType => new UnionType(array_map(fn(ReflectionType $type) => $this->typeFromReflectionType($type), $reflectionType->getTypes())),
            default => throw new \Exception('Cannot denormalize data of type ' . get_class($reflectionType)),
        };
    }

    public function normalizeType(Type $type): Type
    {
        return $type instanceof NormalizableType
            ? $type->normalized()
            : $type;
    }

    public function typeFromParameter(ReflectionParameter $reflectionParameter): Type
    {
        $reflectionType = $reflectionParameter->getType();
        if (! $reflectionType) {
            throw new \Exception('Cannot denormalize data, missing type for parameter ' . $reflectionParameter->getName());
        }

        if ($reflectionType instanceof ReflectionNamedType && $reflectionType->getName() === 'array') {
            return $this->typeFromArrayParameter($reflectionParameter);
        }

        return $this->typeFromReflectionType($reflectionType);
    }
}
