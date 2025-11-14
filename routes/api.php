<?php
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PaymentController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Corremos este endpoint, asi podemos verificar que el servicio esta ok
Route::get('/health', function (Request $request) {
    return response()->json([
        'status' => 'OK',
        'timestamp' => now()->toISOString(),
        'service' => 'Orders & Payments API',
        'version' => '1.0.0',
        'environment' => app()->environment(),
    ]);
});

// Orders routes
Route::prefix('orders')->group(function () {

    Route::get('/', [OrderController::class, 'index'])
        ->name('orders.index');

    Route::post('/', [OrderController::class, 'store'])
        ->name('orders.store');

    Route::get('/stats', [OrderController::class, 'stats'])
        ->name('orders.stats');

    Route::get('/{order}', [OrderController::class, 'show'])
        ->name('orders.show');

    Route::prefix('{order}')->group(function () {

        Route::post('/pay', [PaymentController::class, 'process'])
            ->name('orders.pay');

        Route::get('/payments', [PaymentController::class, 'index'])
            ->name('orders.payments.index');

    });
});


// Fallback route
Route::fallback(function (Request $request) {
    return response()->json([
        'message' => 'Endpoint not found.',
        'requested_url' => $request->url(),
        'available_endpoints' => [
            'GET /api/health',
            'GET /api/orders',
            'POST /api/orders',
            'GET /api/orders/stats',
            'GET /api/orders/{id}',
            'POST /api/orders/{id}/pay',
            'GET /api/orders/{id}/payments',
        ],
        'timestamp' => now()->toISOString(),
    ], 404);
});