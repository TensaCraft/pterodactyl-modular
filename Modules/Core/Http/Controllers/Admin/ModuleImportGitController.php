<?php

namespace Modules\Core\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use InvalidArgumentException;
use Modules\Core\Contracts\ModuleImportServiceInterface;
use Prologue\Alerts\AlertsMessageBag;

class ModuleImportGitController extends Controller
{
    public function __construct(
        private readonly AlertsMessageBag $alert,
        private readonly ModuleImportServiceInterface $imports,
    ) {
    }

    public function __invoke(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'repository' => ['required', 'string', 'max:2048'],
            'reference' => ['nullable', 'string', 'max:255'],
            'install_after_import' => ['nullable', 'boolean'],
            'enable_after_import' => ['nullable', 'boolean'],
            'replace_existing' => ['nullable', 'boolean'],
        ]);

        try {
            $operation = $this->imports->queueGit(
                $validated['repository'],
                $validated['reference'] ?? null,
                install: $request->boolean('install_after_import') || $request->boolean('enable_after_import'),
                enable: $request->boolean('enable_after_import'),
                replaceExisting: $request->boolean('replace_existing'),
            );
        } catch (InvalidArgumentException $exception) {
            return redirect()
                ->route('admin.modules.installation')
                ->withErrors(['module_import' => $exception->getMessage()]);
        }

        $this->alert->success(
            sprintf('Module repository import [%d] has been queued.', $operation->id)
        )->flash();

        return redirect()->route('admin.modules.installation');
    }
}
