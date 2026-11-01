<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;

class ApiProblem
{
    public static function response(
        int $status,
        string $title,
        string $detail,
        string $instance,
        array $extensions = [],
    ): JsonResponse {
        return response()->json([
            'type' => 'about:blank',
            'title' => $title,
            'status' => $status,
            'detail' => $detail,
            'instance' => $instance,
            ...$extensions,
        ], $status, ['Content-Type' => 'application/problem+json']);
    }
}
