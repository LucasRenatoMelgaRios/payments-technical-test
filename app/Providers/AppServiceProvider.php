<?php

namespace App\Providers;

use App\Services\PaymentGatewayService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(PaymentGatewayService::class, function ($app) {
            // Solo usar el fake service cuando se solicite explícitamente
            if (app()->environment('testing') && config('services.payment_gateway.use_fake', false)) {
                return new \App\Services\FakePaymentGatewayService();
            }
            
            return new PaymentGatewayService();
        });
    }

    public function boot(): void
    {
        //
    }
}