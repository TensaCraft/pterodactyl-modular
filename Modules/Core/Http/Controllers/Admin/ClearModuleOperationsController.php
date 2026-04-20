<?php

namespace Modules\Core\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Modules\Core\Contracts\ModuleImportServiceInterface;
use Prologue\Alerts\AlertsMessageBag;

class ClearModuleOperationsController extends Controller
{
    public function __construct(
        private readonly AlertsMessageBag $alert,
        private readonly ModuleImportServiceInterface $imports,
    ) {
    }

    public function __invoke(): RedirectResponse
    {
        $deleted = $this->imports->clearOperations();

        $this->alert->success(sprintf('Cleared %d module operation log record(s).', $deleted))->flash();

        return redirect()->route('admin.modules.logs');
    }
}
