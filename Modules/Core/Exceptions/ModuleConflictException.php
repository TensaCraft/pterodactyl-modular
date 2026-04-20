<?php

namespace Modules\Core\Exceptions;

use InvalidArgumentException;

class ModuleConflictException extends InvalidArgumentException
{
    public static function protectedCoreCannotBeDisabled(): self
    {
        return new self('The protected core module cannot be disabled.');
    }

    public static function forDeclaredConflict(string $module, string $conflict): self
    {
        return new self(sprintf(
            'Module [%s] conflicts with installed module [%s].',
            $module,
            $conflict,
        ));
    }
}
