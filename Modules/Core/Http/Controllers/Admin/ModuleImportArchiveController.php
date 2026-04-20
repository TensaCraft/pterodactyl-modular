<?php

namespace Modules\Core\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Modules\Core\Contracts\ModuleImportServiceInterface;
use Prologue\Alerts\AlertsMessageBag;
use Throwable;

class ModuleImportArchiveController extends Controller
{
    public function __construct(
        private readonly AlertsMessageBag $alert,
        private readonly ModuleImportServiceInterface $imports,
    ) {
    }

    public function __invoke(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'archive' => ['required', 'file', 'mimetypes:application/zip,application/x-zip-compressed'],
            'install_after_import' => ['nullable', 'boolean'],
            'enable_after_import' => ['nullable', 'boolean'],
            'replace_existing' => ['nullable', 'boolean'],
        ]);

        $importsPath = rtrim((string) config('modular.paths.imports'), DIRECTORY_SEPARATOR);
        File::ensureDirectoryExists($importsPath);

        $archive = $validated['archive'];
        $filename = sprintf(
            '%s-%s.%s',
            Str::slug(pathinfo($archive->getClientOriginalName(), PATHINFO_FILENAME) ?: 'module'),
            Str::uuid(),
            $archive->getClientOriginalExtension() ?: 'zip',
        );
        $archivePath = $importsPath . DIRECTORY_SEPARATOR . $filename;
        $archive->move($importsPath, $filename);

        try {
            $operation = $this->imports->queueArchive(
                $archivePath,
                install: $request->boolean('install_after_import') || $request->boolean('enable_after_import'),
                enable: $request->boolean('enable_after_import'),
                replaceExisting: $request->boolean('replace_existing'),
            );
        } catch (Throwable $exception) {
            if (File::exists($archivePath)) {
                File::delete($archivePath);
            }

            if ($exception instanceof InvalidArgumentException) {
                return redirect()
                    ->route('admin.modules.installation')
                    ->withErrors(['module_import' => $exception->getMessage()]);
            }

            throw $exception;
        }

        $this->alert->success(
            sprintf('Module archive import [%d] has been queued.', $operation->id)
        )->flash();

        return redirect()->route('admin.modules.installation');
    }
}
