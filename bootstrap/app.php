<?php

use GuzzleHttp\Psr7\Request;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        //
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (NotFoundHttpException $exception) {
            return response()->json([
                'error' => 'Ngapain?',

            ], Response::HTTP_NOT_FOUND);
        });

        $exceptions->render(function (MethodNotAllowedHttpException $exception) {
            return response()->json([
                'error' => 'ngapain?',

            ], Response::HTTP_NOT_FOUND);
        });
    })
    ->create();
