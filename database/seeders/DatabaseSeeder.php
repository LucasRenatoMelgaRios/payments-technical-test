<?php

namespace Database\Seeders;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Crear órdenes de ejemplo
        $orders = Order::factory()->count(10)->create();

        foreach ($orders as $order) {
            // Crear pagos para algunas órdenes
            if ($order->status === OrderStatus::PAID) {
                Payment::factory()->successful()->forOrder($order)->create();
            } elseif ($order->status === OrderStatus::FAILED) {
                Payment::factory()->failed()->forOrder($order)->create();
            } else {
                // Para órdenes pendientes, crear 0-2 intentos de pago fallidos
                $attempts = rand(0, 2);
                for ($i = 0; $i < $attempts; $i++) {
                    Payment::factory()->failed()->forOrder($order)->create();
                }
            }
        }
    }
}