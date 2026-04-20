<?php

namespace Modules\Core\Exceptions;

use RuntimeException;

class ModuleOperationNotQueuedException extends RuntimeException
{
    public static function forOperation(int $operationId): self
    {
        return new self(sprintf('Module operation [%d] is not queued.', $operationId));
    }
}
