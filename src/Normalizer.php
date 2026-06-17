<?php

namespace SebastianDittrich\Normalizer;

use SebastianDittrich\Normalizer\Type\ArrayType;
use SebastianDittrich\Normalizer\Type\BoolType;
use SebastianDittrich\Normalizer\Type\ClassType;
use SebastianDittrich\Normalizer\Type\EnumType;
use SebastianDittrich\Normalizer\Type\FloatType;
use SebastianDittrich\Normalizer\Type\InterfaceType;
use SebastianDittrich\Normalizer\Type\IntType;
use SebastianDittrich\Normalizer\Type\NullType;
use SebastianDittrich\Normalizer\Type\StringType;
use SebastianDittrich\Normalizer\Type\Type;
use SebastianDittrich\Normalizer\Type\TypeResolver;
use SebastianDittrich\Normalizer\Type\UnionType;
use ReflectionClass;
use ReflectionEnum;
use ReflectionEnumBackedCase;
use ReflectionObject;
use ReflectionParameter;
use ReflectionProperty;

class Normalizer
{
    protected TypeResolver $typeResolver;

    public function __construct()
    {
        $this->typeResolver = new TypeResolver;
    }

    public function serialize(mixed $data): string
    {
        return json_encode($this->normalize($data));
    }

    public function deserialize(string $json, Type|string $type): mixed
    {
        return $this->denormalize(json_decode($json, associative: true), $type);
    }

    public function normalize(mixed $data): mixed
    {
        return match (gettype($data)) {
            'string', 'boolean', 'integer', 'double' => $data,
            'array' => $this->normalizeArray($data),
            'object' => $this->normalizeObject($data),
            'NULL' => null,
            default => throw new NormalizerException('Cannot normalize data of type ' . gettype($data)),
        };
    }

    public function normalizeArray(array $data): array
    {
        return array_map(fn($value) => $this->normalize($value), $data);
    }

    public function normalizeObject(object $data): string|array
    {
        $reflectionObject = new ReflectionObject($data);

        if ($reflectionObject->isEnum()) {
            return $data->value;
        }

        $properties = array_filter(
            $reflectionObject->getProperties(),
            fn(ReflectionProperty $property) => $property->isPublic() && ! $property->isStatic()
        );

        $normalized = array_reduce($properties, function ($carry, ReflectionProperty $property) use ($data) {
            $normalized = $this->normalize($property->getValue($data));
            if ($normalized !== null) {
                $carry[$property->getName()] = $normalized;
            }

            return $carry;
        }, []);

        if ($unionDiscrimintator = Discriminator::forClassInherited($reflectionObject)) {
            $unionDiscrimintator->fill($normalized);
        }
        if ($versionAttribute = Version::forClassInherited($reflectionObject)) {
            $versionAttribute->fill($normalized);
        }

        return $normalized;
    }

    public function denormalize(mixed $data, Type|string $type): mixed
    {
        return $this->denormalizeType($data, $type instanceof Type ? $type : $this->typeResolver->typeFromString($type));
    }

    public function denormalizeEnum(string $data, EnumType $type): object
    {
        return $type->enumName::from($data);
    }

    public function denormalizeClass(array $data, ClassType $type): object
    {
        $reflectionClass = new ReflectionClass($type->className);
        $parameters = $reflectionClass->getConstructor()?->getParameters() ?? []; // If no constructor is defined, the class has no parameters.

        // Versioned classes
        $versionAttribute = Version::forClass($reflectionClass);
        if ($versionAttribute && $reflectionClass->implementsInterface(Versioned::class)) {
            $data = ($type->className)::applyVersion($data[$versionAttribute->fieldname] ?? 1, $data);
        }

        $args = array_map(
            fn(ReflectionParameter $parameter) => $this->denormalizeParameter($data, $parameter),
            $parameters
        );
        $object = $reflectionClass->newInstanceArgs($args);

        return $object;
    }

    public function denormalizeParameter(mixed $data, ReflectionParameter $reflectionParameter)
    {
        $type = $this->typeResolver->typeFromParameter($reflectionParameter);

        if (isset($data[$reflectionParameter->getName()])) {
            return $this->denormalizeType($data[$reflectionParameter->getName()], $type);
        }

        if ($reflectionParameter->allowsNull()) {
            return null;
        }

        throw new NormalizerException('Cannot denormalize data, missing parameter ' . $reflectionParameter->getName() . ' for class ' . $reflectionParameter->getDeclaringClass()->getName());
    }

    public function denormalizeType(mixed $data, Type $type)
    {
        if ($data === null) {
            return null;
        }

        return match (true) {
            $type instanceof StringType => (string) $data,
            $type instanceof IntType => (int) $data,
            $type instanceof BoolType => (bool) $data,
            $type instanceof FloatType => (float) $data,
            $type instanceof UnionType => $this->denormalizeUnion($data, $type),
            $type instanceof ArrayType => $this->denormalizeArray($data, $type),
            $type instanceof InterfaceType => $this->denormalizeInterface($data, $type),
            $type instanceof ClassType => $this->denormalizeClass($data, $type),
            $type instanceof EnumType => $this->denormalizeEnum($data, $type),
            default => throw new NormalizerException('Cannot denormalize data'),
        };
    }

    public function denormalizeArray(mixed $data, ArrayType $type)
    {
        if (! is_array($data)) {
            throw new NormalizerException('Cannot denormalize data, data is not an array');
        }

        return array_map(fn($value) => $this->denormalizeType($value, $type->itemType), $data);
    }

    public function denormalizeUnion(mixed $data, UnionType $unionType)
    {
        $includedTypes = [
            IntType::class => FloatType::class,
            StringType::class => EnumType::class,
        ];
        $enumChecker = function (EnumType $type, mixed $data) {
            try {
                $enum = new ReflectionEnum($type->enumName);
                if (!$enum->isBacked()) {
                    return false;
                }
                $cases = $enum->getCases();
                $caseValues = array_map(fn(ReflectionEnumBackedCase $case) => $case->getBackingValue(), $cases);
                return in_array($data, $caseValues, true);
            } catch (\ReflectionException) {
                return false;
            }
        };

        $possibleTypes = array_filter($unionType->types, fn(Type $type) => match (true) {
            $type instanceof FloatType => is_float($data) || is_int($data),
            $type instanceof IntType => is_int($data),
            $type instanceof EnumType => $enumChecker($type, $data),
            $type instanceof StringType => is_string($data),
            $type instanceof NullType => is_null($data),
            $type instanceof BoolType => is_bool($data),
            $type instanceof ClassType => is_array($data),
            $type instanceof InterfaceType => is_array($data),
            default => throw new NormalizerException('Cannot denormalize data, unsupported union type ' . $type->humanName()),
        });


        if (count($possibleTypes) > 1) {
            $possibleTypes = array_filter($possibleTypes, fn(Type $type) => match (true) {
                $type instanceof ClassType => Discriminator::forClass($type->className)?->check($data),
                $type instanceof InterfaceType => array_any($this->getImplementations($type), fn($implementation) => Discriminator::forClass($implementation)?->check($data)),
                default => true,
            });
        }

        foreach ($includedTypes as $includedType => $includedBy) {
            $is = fn(string $includedType) => fn(Type $type) => is_a($type, $includedType);
            if (array_find($possibleTypes, $is($includedType)) && array_find($possibleTypes, $is($includedBy))) {
                $possibleTypes = array_filter($possibleTypes, fn(Type $type) => !$is($includedType)($type));
            }
        }
        if (count($possibleTypes) > 1) {
            throw new NormalizerException('Cannot denormalize data, multiple possible types found for union ' . $unionType->humanName());
        }
        $type = array_values($possibleTypes)[0];
        if (!$type) {
            throw new NormalizerException('Cannot denormalize data, no matching union type found for ' . $unionType->humanName() . ' with data ' . var_export($data, true));
        }
        return $this->denormalizeType($data, $type);
    }

    private function getImplementations(InterfaceType $interface)
    {
        $reflectionClass = new ReflectionClass($interface->interfaceName);
        return array_reduce(
            $reflectionClass->getAttributes(InterfaceMap::class),
            fn($carry, $attribute) => [
                ...$carry,
                ...$attribute->newInstance()->implementations,
            ],
            []
        );
    }

    public function denormalizeInterface(mixed $data, InterfaceType $type)
    {
        $implementations = $this->getImplementations($type);

        foreach ($implementations as $implementation) {
            $discriminator = Discriminator::forClass($implementation);
            if (! $discriminator) {
                throw new NormalizerException('Cannot denormalize data, missing discriminator attribute for implementation ' . $implementation);
            }
            if (($data[$discriminator->fieldname] ?? false) === $discriminator->value) {
                return $this->denormalize($data, $implementation);
            }
        }

        throw new NormalizerException('Cannot denormalize data, no matching implementation found for interface ' . $reflectionClass->getName() . ' with data ' . var_export($data, true));
    }
}
