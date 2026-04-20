<?php

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Enums\ModuleAction;
use Modules\Core\Enums\ModuleActionStatus;

class CoreModuleOperation extends Model
{
    protected $table = 'core_module_operations';

    protected $fillable = [
        'module_slug',
        'operation',
        'status',
        'payload',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'operation' => ModuleAction::class,
        'status' => ModuleActionStatus::class,
        'payload' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];
}
