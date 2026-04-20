<?php

namespace Modules\Core\Http\Controllers\Admin;

use InvalidArgumentException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Modules\Core\Contracts\ModuleLifecycleServiceInterface;
use Modules\Core\Enums\ModuleAction;
use Prologue\Alerts\AlertsMessageBag;
use ValueError;

class ModuleActionController extends Controller
{
    public function __construct(
        private readonly AlertsMessageBag $alert,
        private readonly ModuleLifecycleServiceInterface $lifecycle,
    ) {
    }

    public function __invoke(string $module, string $action): RedirectResponse
    {
        try {
            $resolvedAction = ModuleAction::from($action);
            $operation = $this->lifecycle->queue($module, $resolvedAction);
        } catch (InvalidArgumentException|ValueError $exception) {
            return redirect()
                ->route('admin.modules.index')
                ->withErrors(['module' => $exception->getMessage()]);
        }

        $this->alert->success(
            sprintf('Module [%s] action [%s] has been queued.', $operation->module_slug, $resolvedAction->value)
        )->flash();

        return redirect()->route('admin.modules.index');
    }
}
