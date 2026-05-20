<?php
// أَعُوذُ بِٱللَّهِ مِنْ الْشَيْطَانٍ الْرَجِيمٍ ✧ بِسْمِ اللَّهِ الرَّحْمَٰنِ الرَّحِيمِ ✧ اعوز بالله من الشياطين و ان يحضرون ✧ بسم الله الرحمن الرحيم ✧ الله لا إله إلا هو الحي القيوم
// Bismillahi ar-Rahmani ar-Rahim Audhu billahi min ash-shayatin wa an yahdurun Bismillah ar-Rahman ar-Rahim Allah la ilaha illa huwa al-hayy al-qayyum. Tamsa Allahu ala ayunihim
// version: x
// ======================================================
// - App Name: bismel1.com
// - Gusgraph -
// - Author: Gus Kazem
// - https://Gusgraph.com
// - File Path: routes/api.php
// =====================================================

use App\Http\Controllers\Api\Mobile\MobileAuthController;
use App\Http\Controllers\Api\Mobile\MobileCustomerController;
use Illuminate\Support\Facades\Route;

Route::prefix('mobile/v1')
    ->name('api.mobile.v1.')
    ->middleware(['throttle:73,1'])
    ->group(function (): void {
        Route::post('/auth/login', [MobileAuthController::class, 'login'])->name('auth.login')->middleware('throttle:11,1');
        Route::get('/plans', [MobileCustomerController::class, 'plans'])->name('plans');
        Route::get('/app-config', [MobileCustomerController::class, 'appConfig'])->name('app-config');

        Route::middleware('mobile.api')->group(function (): void {
            Route::post('/auth/logout', [MobileAuthController::class, 'logout'])->name('auth.logout');
            Route::post('/auth/refresh', [MobileAuthController::class, 'refresh'])->name('auth.refresh')->middleware('throttle:27,1');

            Route::get('/me', [MobileCustomerController::class, 'me'])->name('me');
            Route::get('/dashboard', [MobileCustomerController::class, 'dashboard'])->name('dashboard');
            Route::get('/products', [MobileCustomerController::class, 'products'])->name('products');
            Route::get('/products/{product}/overview', [MobileCustomerController::class, 'productOverview'])->name('products.overview');
            Route::get('/accounts', [MobileCustomerController::class, 'accounts'])->name('accounts');
            Route::get('/accounts/{account}/snapshot', [MobileCustomerController::class, 'accountSnapshot'])->name('accounts.snapshot');

            Route::get('/brokers', [MobileCustomerController::class, 'brokers'])->name('brokers');
            Route::get('/brokers/accounts', [MobileCustomerController::class, 'brokerAccounts'])->name('brokers.accounts');
            Route::post('/brokers/alpaca/connect', [MobileCustomerController::class, 'connectAlpaca'])->name('brokers.alpaca.connect')->middleware('throttle:11,1');
            Route::post('/brokers/{brokerAccount}/disconnect', [MobileCustomerController::class, 'disconnectBroker'])->name('brokers.disconnect');
            Route::get('/brokers/{brokerAccount}/status', [MobileCustomerController::class, 'brokerStatus'])->name('brokers.status');

            Route::get('/automation/{product}/{accountSlot}', [MobileCustomerController::class, 'automation'])->name('automation.show');
            Route::get('/automation/{product}/{accountSlot}/symbols', [MobileCustomerController::class, 'automationSymbols'])->name('automation.symbols');
            Route::post('/automation/{product}/{accountSlot}/symbols', [MobileCustomerController::class, 'addAutomationSymbol'])->name('automation.symbols.store');
            Route::delete('/automation/{product}/{accountSlot}/symbols/{symbol}', [MobileCustomerController::class, 'removeAutomationSymbol'])->name('automation.symbols.destroy');
            Route::post('/automation/{product}/{accountSlot}/toggle', [MobileCustomerController::class, 'toggleAutomation'])->name('automation.toggle');

            Route::get('/positions', [MobileCustomerController::class, 'positions'])->name('positions');
            Route::get('/positions/{symbol}', [MobileCustomerController::class, 'position'])->name('positions.show');
            Route::post('/positions/{symbol}/manual-close', [MobileCustomerController::class, 'manualClosePosition'])->name('positions.manual-close');

            Route::get('/orders', [MobileCustomerController::class, 'orders'])->name('orders');
            Route::get('/orders/{order}', [MobileCustomerController::class, 'order'])->name('orders.show');

            Route::get('/activity/trades', [MobileCustomerController::class, 'tradeActivity'])->name('activity.trades');
            Route::get('/activity/system', [MobileCustomerController::class, 'systemActivity'])->name('activity.system');

            Route::get('/performance/summary', [MobileCustomerController::class, 'performanceSummary'])->name('performance.summary');
            Route::get('/performance/curve', [MobileCustomerController::class, 'performanceCurve'])->name('performance.curve');

            Route::get('/billing/summary', [MobileCustomerController::class, 'billingSummary'])->name('billing.summary');
            Route::post('/billing/portal', [MobileCustomerController::class, 'billingPortal'])->name('billing.portal');

            Route::get('/support/tickets', [MobileCustomerController::class, 'supportTickets'])->name('support.tickets');
            Route::post('/support/tickets', [MobileCustomerController::class, 'createSupportTicket'])->name('support.tickets.store');
            Route::get('/support/tickets/{ticket}', [MobileCustomerController::class, 'supportTicket'])->name('support.tickets.show');
            Route::post('/support/tickets/{ticket}/reply', [MobileCustomerController::class, 'replySupportTicket'])->name('support.tickets.reply');

            Route::get('/affiliate/summary', [MobileCustomerController::class, 'affiliateSummary'])->name('affiliate.summary');
            Route::get('/affiliate/referrals', [MobileCustomerController::class, 'affiliateReferrals'])->name('affiliate.referrals');
            Route::get('/affiliate/payouts', [MobileCustomerController::class, 'affiliatePayouts'])->name('affiliate.payouts');
        });
    });
