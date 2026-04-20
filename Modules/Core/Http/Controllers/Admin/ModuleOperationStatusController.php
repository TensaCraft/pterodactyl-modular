<?php

namespace Modules\Core\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Core\Contracts\ModuleLifecycleServiceInterface;

class ModuleOperationStatusController extends Controller
{
    public function __construct(private readonly ModuleLifecycleServiceInterface $lifecycle)
    {
    }

    public function __invoke(string $module): JsonResponse
    {
        $operation = $this->lifecycle->latestOperationFor($module);

        return response()->json([
            'data' => [
                'module' => $module,
                'operation' => $operation === null ? null : [
                    'id' => $operation->id,
                    'action' => $operation->operation->value,
                    'status' => $operation->status->value,
                    'error' => $operation->payload['error'] ?? null,
                    'progress' => $operation->payload['progress'] ?? null,
                    'steps' => $operation->payload['steps'] ?? [],
                    'started_at' => $operation->started_at?->toIso8601String(),
                    'finished_at' => $operation->finished_at?->toIso8601String(),
                ],
            ],
        ]);
    }
}
