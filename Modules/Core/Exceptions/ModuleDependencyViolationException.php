<?php

namespace Modules\Core\Exceptions;

use InvalidArgumentException;

class ModuleDependencyViolationException extends InvalidArgumentException
{
    public static function forMissingDependency(string $module, string $dependency): self
    {
        return new self(sprintf(
            'Module [%s] cannot be enabled because dependency [%s] is not enabled.',
            $module,
            $dependency,
        ));
    }

    public static function forEnabledDependent(string $module, string $dependent): self
    {
        return new self(sprintf(
            'Module [%s] cannot be disabled because dependent [%s] is still enabled.',
            $module,
            $dependent,
        ));
    }
}
