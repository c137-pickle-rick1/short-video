<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\ShortVideo\Services\RuntimeStateStore;
use Illuminate\Http\JsonResponse;

final class SystemController extends Controller
{
    public function health(RuntimeStateStore $runtimeStateStore): JsonResponse
    {
        $state = $runtimeStateStore->getBackoffState();

        return response()->json([
            'ok' => true,
            'backoffUntil' => $state['backoffUntil'],
            'backoffReason' => $state['backoffReason'],
        ]);
    }
}
