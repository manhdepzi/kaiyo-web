<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Modules\Foundation\Application\ReadReadiness;
use Illuminate\Http\JsonResponse;

final class ReadinessController
{
    public function __invoke(ReadReadiness $readiness): JsonResponse
    {
        $result = $readiness->execute();

        return response()->json($result, $result['status'] === 'ready' ? 200 : 503);
    }
}
