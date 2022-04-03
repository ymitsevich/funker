<?php

namespace Ymitsevich\Funker;

use Nelmio\Alice\Loader\NativeLoader;
use Nelmio\Alice\PropertyAccess\ReflectionPropertyAccessor;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

class WithReflectionLoader extends NativeLoader
{
    protected function createPropertyAccessor(): PropertyAccessorInterface
    {
        return new ReflectionPropertyAccessor(parent::createPropertyAccessor());
    }
}
