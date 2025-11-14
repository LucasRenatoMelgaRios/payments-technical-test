<?php

namespace App\Actions;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Services\PaymentGatewayService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessPaymentAction
{
    public function __construct(
        private PaymentGatewayService $paymentGatewayService,
        private UpdateOrderStatusAction $updateOrderStatusAction
    ) {}

    /**
     * Procesar pago para un pedido
     */
    public function execute(Order $order): Payment
    {
        // Prevenir procesamiento duplicado con lock
        $lock = Cache::lock("order_payment_{$order->id}", 5);
        
        if (!$lock->get()) {
            Log::warning('Payment processing locked - duplicate attempt', [
                'order_id' => $order->id,
                'customer' => $order->customer_name
            ]);
            
            throw new \Exception('El pago para este pedido ya está siendo procesado. Por favor, espere unos segundos.');
        }

        try {
            return DB::transaction(function () use ($order) {
                // Verificar que el pedido puede recibir pagos
                $this->validateOrderCanReceivePayment($order);

                Log::info('Processing payment', [
                    'order_id' => $order->id,
                    'amount' => $order->amount,
                    'current_status' => $order->status
                ]);

                // Procesar pago con gateway externo
                $paymentResult = $this->paymentGatewayService->charge($order);

                // Registrar pago en base de datos
                $payment = $this->createPaymentRecord($order, $paymentResult);

                // Actualizar estado del pedido según resultado
                $this->updateOrderStatus($order, $paymentResult, $payment);

                Log::info('Payment processed', [
                    'order_id' => $order->id,
                    'payment_id' => $payment->id,
                    'payment_status' => $payment->status,
                    'new_order_status' => $order->status,
                    'gateway_response' => $paymentResult['message'] ?? 'No message'
                ]);

                return $payment;
            });
        } finally {
            $lock->release();
        }
    }

    /**
     * Validar que el pedido puede recibir pagos
     */
    private function validateOrderCanReceivePayment(Order $order): void
    {
        if ($order->isPaid()) {
            throw new \Exception('El pedido ya ha sido pagado y no acepta nuevos pagos.');
        }

        if (!$order->isPending() && !$order->isFailed()) {
            throw new \Exception('El pedido no está en un estado que permita procesar pagos.');
        }
    }

    /**
     * Crear registro de pago
     */
    private function createPaymentRecord(Order $order, array $paymentResult): Payment
    {
        return Payment::create([
            'order_id' => $order->id,
            'status' => $paymentResult['success'] ? 'success' : 'failed',
            'external_response' => $paymentResult,
        ]);
    }

    /**
     * Actualizar estado del pedido
     */
    private function updateOrderStatus(Order $order, array $paymentResult, Payment $payment): void
    {
        $newStatus = $paymentResult['success'] 
            ? OrderStatus::PAID 
            : OrderStatus::FAILED;
            
        $this->updateOrderStatusAction->execute($order, $newStatus, $payment);
    }

    /**
     * Obtener estadísticas de procesamiento
     */
    public function getProcessingStats(Order $order): array
    {
        return [
            'total_attempts' => $order->payments()->count(),
            'successful_attempts' => $order->payments()->successful()->count(),
            'failed_attempts' => $order->payments()->failed()->count(),
            'last_attempt' => $order->last_payment?->created_at,
        ];
    }
}
