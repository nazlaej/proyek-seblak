<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;

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

        // Problem Details untuk 422 validasi (RFC 7807)
        $exceptions->render(function (ValidationException $e, Request $request) {
            return response()->json([
                'type'  => 'urn:seblak:problem:validasi-gagal',
                'title' => 'Data tidak valid',
                'status'=> 422,
                'detail'=> $e->validator->errors()->first(),
                'instance'=> $request->path(),
            ], 422, [
                'Content-Type' => 'application/problem+json',
            ]);
        });

        // Problem Details untuk 404 model tidak ditemukan
        $exceptions->render(function (ModelNotFoundException $e, Request $request) {
            return response()->json([
                'type'  => 'urn:seblak:problem:not-found',
                'title' => 'Pesanan tidak ditemukan',
                'status'=> 404,
                'detail'=> 'Pesanan dengan nomor tersebut tidak ada.',
                'instance'=> $request->path(),
            ], 404, [
                'Content-Type' => 'application/problem+json',
            ]);
        });
    })->create();
