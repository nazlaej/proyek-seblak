<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(function (\Throwable $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            if ($exception instanceof ValidationException) {
                $errors = $exception->errors();
                $detail = collect($errors)->flatten()->first() ?? 'Data yang dikirim tidak valid.';

                return \App\Support\ApiProblem::response(
                    422,
                    'Data tidak valid',
                    $detail,
                    '/'.$request->path(),
                    ['errors' => $errors],
                );
            }

            $status = $exception instanceof HttpExceptionInterface
                ? $exception->getStatusCode()
                : 500;
            $title = match ($status) {
                404 => 'Sumber daya tidak ditemukan',
                405 => 'Metode tidak diizinkan',
                409 => 'Konflik',
                429 => 'Terlalu banyak permintaan',
                default => $status >= 500 ? 'Kesalahan server' : 'Permintaan gagal',
            };
            $detail = $status >= 500
                ? 'Terjadi kesalahan pada server.'
                : ($exception->getMessage() ?: $title);

            return \App\Support\ApiProblem::response(
                $status,
                $title,
                $detail,
                '/'.$request->path(),
            );
        });
    })->create();
