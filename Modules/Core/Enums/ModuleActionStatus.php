<?php

namespace Modules\Core\Enums;

enum ModuleActionStatus: string
{
    case Queued = 'queued';
    case Running = 'running';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
}
