<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PaymentGatewayService
{
    private string $baseUrl;
    private int $timeout;
    private int $retries;
    private string $mockStrategy;

    public function __construct()
    {
        $this->baseUrl = config('services.payment_gateway.url', 'https://fake-payment-gateway.free.beeceptor.com');
        $this->timeout = config('services.payment_gateway.timeout', 10);
        $this->retries = config('services.payment_gateway.retries', 3);
        $this->mockStrategy = config('services.payment_gateway.mock_strategy', 'amount_based');
    }

    /**
     * Procesar cargo en el gateway de pago
     */
    public function charge(Order $order): array
    {
        $payload = $this->buildPayload($order);

        // Determinar endpoint
        $endpoint = $this->determineEndpoint($order);

        Log::info('Processing payment with Beeceptor', [
            'order_id' => $order->id,
            'endpoint' => $endpoint,
            'strategy' => $this->mockStrategy,
            'amount' => $order->amount,
            'full_url' => $this->baseUrl . $endpoint
        ]);

        try {
            $response = Http::timeout($this->timeout)
                ->retry($this->retries, 100)
                ->withHeaders($this->getHeaders())
                ->post($this->baseUrl . $endpoint, $payload);

            return $this->processBeeceptorResponse($response, $order, $endpoint);

        } catch (\Exception $e) {
            Log::error('Beeceptor API exception', [
                'order_id' => $order->id,
                'exception' => $e->getMessage(),
                'url' => $this->baseUrl . $endpoint
            ]);

            return $this->buildErrorResponse('Beeceptor API Exception: ' . $e->getMessage());
        }
    }

    /**
     * Determinar qué endpoint usar
     */
    private function determineEndpoint(Order $order): string
    {
        // Si estamos en entorno de testing, usar la estrategia configurada
        if (app()->environment('testing')) {
            return $this->getEndpointForTesting($order);
        }

        return match($this->mockStrategy) {
            'always_success' => '/payment-success',
            'always_failure' => '/payment-failed',
            'amount_based' => $this->getEndpointByAmount((float) $order->amount),
            'random' => $this->getRandomEndpoint(),
            default => $this->getEndpointByAmount((float) $order->amount),
        };
    }

    /**
     * Endpoint para testing - más predecible
     */
    private function getEndpointForTesting(Order $order): string
    {
        // En testing, usar siempre los endpoints basados en monto
        return $this->getEndpointByAmount((float) $order->amount);
    }

    /**
     * Endpoint basado en monto
     */
    private function getEndpointByAmount(float $amount): string
    {
        $amountInCents = (int) round($amount * 100); // Usar round para evitar errores de precisión
        Log::debug('Amount in cents for endpoint decision', [
            'amount' => $amount,
            'amount_in_cents' => $amountInCents,
            'is_even' => $amountInCents % 2 === 0
        ]);
        
        return ($amountInCents % 2 === 0) ? '/payment-success' : '/payment-failed';
    }

    private function getRandomEndpoint(): string
    {
        return (rand(0, 1) === 1) ? '/payment-success' : '/payment-failed';
    }

    private function processBeeceptorResponse($response, Order $order, string $endpoint): array
    {
        $logContext = [
            'order_id' => $order->id,
            'endpoint' => $endpoint,
            'http_status' => $response->status(),
            'url' => $this->baseUrl . $endpoint
        ];

        // Si hay error HTTP, considerar como fallo
        if (!$response->successful()) {
            Log::error('Beeceptor API HTTP error', array_merge($logContext, [
                'response_body' => $response->body(),
            ]));

            return [
                'success' => false,
                'message' => 'Beeceptor API HTTP Error: ' . $response->status(),
                'external_id' => null,
                'gateway_status' => 'http_error',
                'data' => null,
                'http_status' => $response->status(),
                'mock_endpoint' => $endpoint,
                'mock_strategy' => $this->mockStrategy,
            ];
        }

        $data = $response->json();
        
        // Verificar que la respuesta tiene la estructura esperada
        $isSuccess = isset($data['status']) && $data['status'] === 'approved';
        
        Log::info('Beeceptor API response', array_merge($logContext, [
            'gateway_status' => $data['status'] ?? 'unknown',
            'success' => $isSuccess,
            'message' => $data['message'] ?? 'No message'
        ]));

        return [
            'success' => $isSuccess,
            'message' => $data['message'] ?? ($isSuccess ? 'Pago procesado exitosamente' : 'El pago fue rechazado'),
            'external_id' => $data['transaction_id'] ?? null,
            'gateway_status' => $data['status'] ?? 'unknown',
            'data' => $data,
            'http_status' => $response->status(),
            'mock_endpoint' => $endpoint,
            'mock_strategy' => $this->mockStrategy,
        ];
    }

    private function buildPayload(Order $order): array
    {
        return [
            'order_id' => $order->id,
            'amount' => $order->amount,
            'customer_name' => $order->customer_name,
            'currency' => 'USD',
            'timestamp' => now()->toISOString(),
            'reference' => 'ORD_' . $order->id . '_' . now()->timestamp,
            'mock_strategy' => $this->mockStrategy,
        ];
    }

    private function getHeaders(): array
    {
        return [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'User-Agent' => 'Laravel-Orders-API/1.0',
            'X-Mock-Strategy' => $this->mockStrategy,
        ];
    }

    private function buildErrorResponse(string $message, int $httpStatus = 500): array
    {
        return [
            'success' => false,
            'message' => $message,
            'external_id' => null,
            'gateway_status' => 'error',
            'data' => null,
            'http_status' => $httpStatus,
        ];
    }

    public function setMockStrategy(string $strategy): self
    {
        $validStrategies = ['always_success', 'always_failure', 'amount_based', 'random'];

        if (!in_array($strategy, $validStrategies)) {
            throw new \InvalidArgumentException("Estrategia de mock inválida: {$strategy}");
        }

        $this->mockStrategy = $strategy;
        return $this;
    }

    public function getConfig(): array
    {
        return [
            'base_url' => $this->baseUrl,
            'timeout' => $this->timeout,
            'retries' => $this->retries,
            'mock_strategy' => $this->mockStrategy,
            'endpoints' => [
                'success' => '/payment-success',
                'failure' => '/payment-failed',
            ],
        ];
    }
}