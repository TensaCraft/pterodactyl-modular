<?php

use Illuminate\Support\Facades\Route;
use Modules\Core\Http\Controllers\Admin\ModuleActionController;
use Modules\Core\Http\Controllers\Admin\ModuleImportArchiveController;
use Modules\Core\Http\Controllers\Admin\ModuleImportGitController;
use Modules\Core\Http\Controllers\Admin\ModuleIndexController;
use Modules\Core\Http\Controllers\Admin\ModuleOperationsFeedController;
use Modules\Core\Http\Controllers\Admin\ModuleOperationStatusController;
use Modules\Core\Http\Controllers\Admin\ClearModuleOperationsController;
use Pterodactyl\Http\Middleware\AdminAuthenticate;
use Pterodactyl\Http\Middleware\RequireTwoFactorAuthentication;

Route::middleware(['web', 'auth.session', RequireTwoFactorAuthentication::class, AdminAuthenticate::class])
    ->prefix('/admin/modules')
    ->group(function () {
        Route::get('/', [ModuleIndexController::class, 'index'])->name('admin.modules.index');
        Route::get('/logs', [ModuleIndexController::class, 'logs'])->name('admin.modules.logs');
        Route::get('/installation', [ModuleIndexController::class, 'installation'])->name('admin.modules.installation');
        Route::get('/operations', ModuleOperationsFeedController::class)->name('admin.modules.operations.index');
        Route::post('/operations/clear', ClearModuleOperationsController::class)->name('admin.modules.operations.clear');
        Route::post('/import/archive', ModuleImportArchiveController::class)->name('admin.modules.import.archive');
        Route::post('/import/git', ModuleImportGitController::class)->name('admin.modules.import.git');
        Route::get('/{module}/status', ModuleOperationStatusController::class)->name('admin.modules.status');
        Route::post('/{module}/{action}', ModuleActionController::class)->name('admin.modules.action');
    });
