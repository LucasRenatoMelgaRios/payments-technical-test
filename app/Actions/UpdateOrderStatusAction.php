<?php

namespace App\Actions;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Support\Facades\Log;

class UpdateOrderStatusAction
{
    /**
     * Actualizar estado del pedido
     */
public function execute(Order $order, OrderStatus $newStatus, ?Payment $payment = null): void
{
    $oldStatus = $order->status instanceof OrderStatus
        ? $order->status->value
        : $order->status;

    $order->status = $newStatus;
    $order->save();

    $this->triggerStatusEvents($order, $newStatus, $oldStatus, $payment);
}

    /**
     * Disparar eventos según cambio de estado
     */
    private function triggerStatusEvents(Order $order, OrderStatus $newStatus, string $oldStatus, ?Payment $payment): void
    {
        switch ($newStatus) {
            case OrderStatus::PAID:
                $this->handlePaidStatus($order, $payment);
                break;
                
            case OrderStatus::FAILED:
                $this->handleFailedStatus($order, $payment);
                break;
                
            case OrderStatus::PENDING:
                $this->handlePendingStatus($order, $oldStatus);
                break;
        }
    }

    /**
     * Manejar estado PAID
     */
    private function handlePaidStatus(Order $order, ?Payment $payment): void
    {
        Log::info('Order marked as paid', [
            'order_id' => $order->id,
            'payment_id' => $payment?->id,
            'amount' => $order->amount,
            'customer' => $order->customer_name
        ]);

        // Aquí se podrían disparar eventos como:
        // - Envío de email de confirmación
        // - Notificación al equipo
        // - Actualización de inventario
        // OrderPaid::dispatch($order, $payment);
    }

    /**
     * Manejar estado FAILED
     */
    private function handleFailedStatus(Order $order, ?Payment $payment): void
    {
        $attempts = $order->payment_attempts;
        
        Log::warning('Order marked as failed', [
            'order_id' => $order->id,
            'payment_id' => $payment?->id,
            'attempts' => $attempts,
            'customer' => $order->customer_name,
            'gateway_message' => $payment?->external_message
        ]);

        // Aquí se podrían disparar eventos como:
        // - Notificación al cliente para reintentar
        // - Alerta al equipo después de X intentos fallidos
        // OrderFailed::dispatch($order, $payment, $attempts);
    }

    /**
     * Manejar estado PENDING
     */
    private function handlePendingStatus(Order $order, string $oldStatus): void
    {
        // Solo log si viene de un estado diferente
        if ($oldStatus !== OrderStatus::PENDING->value) {
            Log::info('Order reset to pending', [
                'order_id' => $order->id,
                'previous_status' => $oldStatus,
                'customer' => $order->customer_name
            ]);
        }
    }

    /**
     * Validar transición de estado
     */
    public function isValidTransition(string $fromStatus, OrderStatus $toStatus): bool
    {
        $transitions = [
            OrderStatus::PENDING->value => [OrderStatus::PAID, OrderStatus::FAILED],
            OrderStatus::FAILED->value => [OrderStatus::PAID, OrderStatus::PENDING],
            OrderStatus::PAID->value => [], // No se permiten cambios desde paid
        ];

        return in_array($toStatus, $transitions[$fromStatus] ?? []);
    }
}