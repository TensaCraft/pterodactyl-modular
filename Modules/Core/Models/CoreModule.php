<?php

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Enums\ModuleState;

class CoreModule extends Model
{
    protected $table = 'core_modules';

    protected $fillable = [
        'name',
        'slug',
        'version',
        'description',
        'priority',
        'state',
        'is_protected',
        'has_frontend',
        'panel_constraint',
        'core_api_constraint',
        'manifest_path',
    ];

    protected $casts = [
        'priority' => 'integer',
        'is_protected' => 'boolean',
        'has_frontend' => 'boolean',
        'state' => ModuleState::class,
    ];
}
