<?php

namespace App\Actions;

use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Support\Facades\DB;

class CreateOrderAction
{
    /**
     * Crear un nuevo pedido
     */
    public function execute(array $data): Order
    {
        return DB::transaction(function () use ($data) {
            $order = Order::create([
                'customer_name' => $data['customer_name'],
                'amount' => $data['amount'],
                'status' => OrderStatus::PENDING,
            ]);

            // Log para tracking
            \Log::info('Order created', [
                'order_id' => $order->id,
                'customer' => $order->customer_name,
                'amount' => $order->amount,
                'status' => $order->status
            ]);

            return $order->load('payments');
        });
    }

    /**
     * Validar datos antes de crear el pedido
     */
    public function validate(array $data): bool
    {
        return isset($data['customer_name']) && 
               isset($data['amount']) && 
               is_numeric($data['amount']) && 
               $data['amount'] > 0;
    }
}