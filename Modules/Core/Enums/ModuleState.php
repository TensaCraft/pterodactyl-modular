<?php

namespace Modules\Core\Enums;

enum ModuleState: string
{
    case Discovered = 'discovered';
    case Installed = 'installed';
    case Enabled = 'enabled';
    case Disabled = 'disabled';
    case Updating = 'updating';
    case Error = 'error';
    case Incompatible = 'incompatible';
}
