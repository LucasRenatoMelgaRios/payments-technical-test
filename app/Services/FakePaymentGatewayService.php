<?php

namespace App\Services;

use App\Models\Order;

class FakePaymentGatewayService extends PaymentGatewayService
{
    private bool $shouldSucceed;
    private ?string $customMessage;
    private ?string $transactionId;

    public function __construct(bool $shouldSucceed = true, ?string $customMessage = null, ?string $transactionId = null)
    {
        $this->shouldSucceed = $shouldSucceed;
        $this->customMessage = $customMessage;
        $this->transactionId = $transactionId;

        // Configurar URL falsa
        config(['services.payment_gateway.url' => 'https://fake-payment-gateway.test']);
    }

    /**
     * Simular cargo en gateway de pago
     */
    public function charge(Order $order): array
    {
        // Simular delay de red
        usleep(100000); // 100ms

        if ($this->shouldSucceed) {
            return [
                'success' => true,
                'message' => $this->customMessage ?? 'Pago procesado exitosamente',
                'external_id' => $this->transactionId ?? 'txn_fake_' . now()->timestamp,
                'gateway_status' => 'approved',
                'data' => [
                    'status' => 'approved',
                    'message' => $this->customMessage ?? 'Pago procesado exitosamente',
                    'transaction_id' => $this->transactionId ?? 'txn_fake_' . now()->timestamp,
                    'amount_charged' => $order->amount,
                    'currency' => 'USD',
                    'timestamp' => now()->toISOString(),
                ],
                'http_status' => 200,
            ];
        }

        return [
            'success' => false,
            'message' => $this->customMessage ?? 'Fondos insuficientes',
            'external_id' => null,
            'gateway_status' => 'declined',
            'data' => [
                'status' => 'declined',
                'message' => $this->customMessage ?? 'Fondos insuficientes',
                'error_code' => 'INSUFFICIENT_FUNDS',
                'timestamp' => now()->toISOString(),
            ],
            'http_status' => 200,
        ];
    }

    /**
     * Forzar éxito en próximas llamadas
     */
    public function forceSuccess(?string $message = null, ?string $transactionId = null): self
    {
        $this->shouldSucceed = true;
        $this->customMessage = $message;
        $this->transactionId = $transactionId;

        return $this;
    }

    /**
     * Forzar fallo en próximas llamadas
     */
    public function forceFailure(?string $message = null): self
    {
        $this->shouldSucceed = false;
        $this->customMessage = $message;
        $this->transactionId = null;

        return $this;
    }

    /**
     * Simular timeout
     */
    public function simulateTimeout(): self
    {
        $this->shouldSucceed = false;
        $this->customMessage = 'Gateway timeout';

        return $this;
    }

    /**
     * Health check falso
     */
    public function healthCheck(): array
    {
        return [
            'healthy' => true,
            'status' => 200,
            'response_time' => 50, // ms
            'fake_service' => true,
        ];
    }
}
