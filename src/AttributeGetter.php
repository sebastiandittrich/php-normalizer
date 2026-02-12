<?php

namespace SebastianDittrich\Normalizer;

use ReflectionClass;
use ReflectionParameter;

trait AttributeGetter
{
    public static function forClass(string|ReflectionClass $class): ?self
    {
        $reflectionClass = $class instanceof ReflectionClass ? $class : new ReflectionClass($class);
        $attribute = $reflectionClass->getAttributes(self::class)[0] ?? null;

        return $attribute?->newInstance();
    }

    public static function forClassInherited(string|ReflectionClass $class): ?self
    {
        $reflectionClass = $class instanceof ReflectionClass ? $class : new ReflectionClass($class);

        $attribute = self::forClass($reflectionClass);
        if ($attribute) {
            return $attribute;
        }

        $parentClass = $reflectionClass->getParentClass();
        if (! $parentClass) {
            return null;
        }

        return self::forClassInherited($parentClass->getName());
    }

    public static function resolve(string|object $target): ?self
    {
        $target = match (true) {
            $target instanceof ReflectionClass => $target,
            $target instanceof ReflectionParameter => $target,
            default => new ReflectionClass($target),
        };

        $attribute = $target->getAttributes(self::class)[0] ?? null;

        return $attribute?->newInstance();
    }
}
