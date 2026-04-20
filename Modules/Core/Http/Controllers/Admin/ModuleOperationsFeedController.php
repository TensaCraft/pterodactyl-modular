<?php

namespace Modules\Core\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Core\Contracts\ModuleImportServiceInterface;

class ModuleOperationsFeedController extends Controller
{
    public function __construct(private readonly ModuleImportServiceInterface $imports)
    {
    }

    public function __invoke(): JsonResponse
    {
        $operations = $this->imports->latestOperations(20)->map(static function ($operation): array {
            return [
                'id' => $operation->id,
                'module_slug' => $operation->module_slug,
                'action' => $operation->operation->value,
                'status' => $operation->status->value,
                'progress' => $operation->payload['progress'] ?? null,
                'error' => $operation->payload['error'] ?? null,
                'steps' => $operation->payload['steps'] ?? [],
                'source' => $operation->payload['source'] ?? null,
                'started_at' => $operation->started_at?->toIso8601String(),
                'finished_at' => $operation->finished_at?->toIso8601String(),
            ];
        })->values();

        return response()->json([
            'data' => $operations,
        ]);
    }
}
