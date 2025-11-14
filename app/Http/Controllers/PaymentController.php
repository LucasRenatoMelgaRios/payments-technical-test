<?php

namespace App\Http\Controllers;

use App\Actions\ProcessPaymentAction;
use App\Http\Requests\StorePaymentRequest;
use App\Http\Resources\PaymentResource;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    public function __construct(
        private ProcessPaymentAction $processPayment
    ) {}

    /**
     * Procesar pago para un pedido
     */
    public function process(StorePaymentRequest $request, Order $order): JsonResponse
    {
        try {
            Log::info('Processing payment for order', [
                'order_id' => $order->id,
                'current_status' => $order->status,
                'amount' => $order->amount
            ]);

            // Procesar el pago
            $payment = $this->processPayment->execute($order);

            // Preparar respuesta
            $response = [
                'message' => $payment->isSuccessful() 
                    ? 'Pago procesado exitosamente' 
                    : 'El pago no pudo ser procesado',
                'payment' => new PaymentResource($payment),
                'order' => [
                    'id' => $order->id,
                    'status' => $order->status,
                    'customer_name' => $order->customer_name,
                    'amount' => $order->amount,
                ],
                'attempts' => $this->processPayment->getProcessingStats($order),
            ];

            Log::info('Payment processing completed', [
                'order_id' => $order->id,
                'payment_id' => $payment->id,
                'payment_status' => $payment->status,
                'new_order_status' => $order->status
            ]);

            return response()->json($response, 201);

        } catch (\Exception $e) {
            Log::error('Error processing payment', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $statusCode = $this->getStatusCodeForException($e);

            return response()->json([
                'message' => 'Error al procesar el pago',
                'error' => config('app.debug') ? $e->getMessage() : 'Error interno del servidor',
                'order_id' => $order->id,
                'current_status' => $order->status
            ], $statusCode);
        }
    }

    /**
     * Obtener historial de pagos de un pedido
     */
    public function index(Order $order): JsonResponse
    {
        try {
            Log::info('Fetching payment history for order', ['order_id' => $order->id]);

            $payments = $order->payments()
                ->latest()
                ->paginate(10);

            $stats = $this->processPayment->getProcessingStats($order);

            return response()->json([
                'data' => PaymentResource::collection($payments),
                'meta' => [
                    'order_id' => $order->id,
                    'order_status' => $order->status,
                    'stats' => $stats,
                    'pagination' => [
                        'total' => $payments->total(),
                        'per_page' => $payments->perPage(),
                        'current_page' => $payments->currentPage(),
                        'last_page' => $payments->lastPage(),
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching payment history', [
                'order_id' => $order->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'message' => 'Error al obtener el historial de pagos',
                'error' => config('app.debug') ? $e->getMessage() : 'Error interno del servidor'
            ], 500);
        }
    }

    /**
     * Obtener código de estado HTTP apropiado para la excepción
     */
    private function getStatusCodeForException(\Exception $e): int
    {
        return match (true) {
            str_contains($e->getMessage(), 'ya ha sido pagado') => 422,
            str_contains($e->getMessage(), 'ya está siendo procesado') => 409,
            str_contains($e->getMessage(), 'no está en un estado') => 422,
            default => 500,
        };
    }
}