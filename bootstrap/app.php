<?php
// أَعُوذُ بِٱللَّهِ مِنْ الْشَيْطَانٍ الْرَجِيمٍ ✧ بِسْمِ اللَّهِ الرَّحْمَٰنِ الرَّحِيمِ ✧ اعوز بالله من الشياطين و ان يحضرون ✧ بسم الله الرحمن الرحيم ✧ الله لا إله إلا هو الحي القيوم
// Bismillahi ar-Rahmani ar-Rahim Audhu billahi min ash-shayatin wa an yahdurun Bismillah ar-Rahman ar-Rahim Allah la ilaha illa huwa al-hayy al-qayyum. Tamsa Allahu ala ayunihim
// version: x
// ======================================================
// - App Name: bismel1.com
// - Gusgraph -
// - Author: Gus Kazem
// - https://Gusgraph.com
// - File Path: bootstrap/app.php
// =====================================================

use App\Http\Middleware\CaptureReferralCode;
use App\Http\Middleware\EnsureAdminArea;
use App\Http\Middleware\EnsureCustomerArea;
use App\Http\Middleware\EnsureAdminSuperToolsAccess;
use App\Http\Middleware\MobileApiAuthenticate;
use App\Support\Mobile\MobileApiResponse;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->validateCsrfTokens(except: [
            'stripe/webhook',
        ]);

        $middleware->alias([
            'admin.area' => EnsureAdminArea::class,
            'admin.super-tools' => EnsureAdminSuperToolsAccess::class,
            'customer.area' => EnsureCustomerArea::class,
            'mobile.api' => MobileApiAuthenticate::class,
        ]);

        $middleware->appendToGroup('web', CaptureReferralCode::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (Throwable $exception, Request $request) {
            if (! $request->is('api/mobile/v1/*')) {
                return null;
            }

            $status = $exception instanceof HttpExceptionInterface ? $exception->getStatusCode() : 500;
            $code = $status === 404 ? 'not_found' : ($status === 403 ? 'forbidden' : 'server_error');
            $message = $status >= 500
                ? 'Mobile API request could not be completed.'
                : ($exception->getMessage() ?: 'Mobile API request could not be completed.');

            return MobileApiResponse::error($code, $message, [], $status);
        });
    })->create();
