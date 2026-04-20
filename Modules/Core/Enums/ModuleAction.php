<?php

namespace Modules\Core\Enums;

enum ModuleAction: string
{
    case Install = 'install';
    case Update = 'update';
    case Enable = 'enable';
    case Disable = 'disable';
    case RebuildRegistry = 'rebuild-registry';
    case ImportArchive = 'import-archive';
    case ImportGit = 'import-git';
}
