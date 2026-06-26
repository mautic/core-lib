<?php

declare(strict_types=1);

namespace Mautic\CoreBundle\Test;

final class ReflectionHelper
{
    /**
     * Sets a property value on an object via reflection, bypassing visibility.
     *
     * Pass $class to target a private property declared on a parent class
     * (e.g. when $object is a mock); otherwise the object's own class is used.
     */
    public static function setValue(object $object, string $property, mixed $value, ?string $class = null): void
    {
        $reflectionProperty = new \ReflectionProperty($class ?? $object::class, $property);
        $reflectionProperty->setValue($object, $value);
    }

    /**
     * Sets a static property value on a class via reflection, bypassing visibility.
     */
    public static function setStaticValue(string $class, string $property, mixed $value): void
    {
        $reflectionProperty = new \ReflectionProperty($class, $property);
        $reflectionProperty->setValue(null, $value);
    }
}
