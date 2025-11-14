<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ProcessPaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Asegurarnos de que no se use el fake service
        config(['services.payment_gateway.use_fake' => false]);
    }

    /**
     * Test successful payment with amount-based strategy (even amount)
     */
    public function test_successful_payment_with_even_amount(): void
    {
        Http::fake([
            'fake-payment-gateway.free.beeceptor.com/payment-success' => Http::response([
                'status' => 'approved',
                'message' => 'Payment processed successfully',
                'transaction_id' => 'txn_12345',
                'amount_charged' => 100.00,
                'currency' => 'USD',
                'timestamp' => now()->toISOString(),
            ], 200)
        ]);

        $order = Order::factory()->create([
            'status' => OrderStatus::PENDING,
            'amount' => 100.00 // Even amount -> should use success endpoint
        ]);

        $response = $this->postJson("/api/orders/{$order->id}/pay");

        $response->assertStatus(201)
            ->assertJsonPath('payment.status', 'success')
            ->assertJsonPath('order.status', OrderStatus::PAID->value);

        // Verificar que se llamó al endpoint correcto
        Http::assertSent(function ($request) use ($order) {
            return $request->url() === 'https://fake-payment-gateway.free.beeceptor.com/payment-success' &&
                   $request->method() === 'POST' &&
                   $request->data()['order_id'] === $order->id;
        });
    }

    /**
     * Test failed payment with amount-based strategy (odd amount)
     */
    public function test_failed_payment_with_odd_amount(): void
    {
        Http::fake([
            'fake-payment-gateway.free.beeceptor.com/payment-failed' => Http::response([
                'status' => 'declined',
                'message' => 'Insufficient funds',
                'error_code' => 'INSUFFICIENT_FUNDS',
                'timestamp' => now()->toISOString(),
            ], 200)
        ]);

        $order = Order::factory()->create([
            'status' => OrderStatus::PENDING,
            'amount' => 99.99 // Odd amount -> should use failure endpoint
        ]);

        $response = $this->postJson("/api/orders/{$order->id}/pay");

        $response->assertStatus(201)
            ->assertJsonPath('payment.status', 'failed')
            ->assertJsonPath('order.status', OrderStatus::FAILED->value);

        // Verificar que se llamó al endpoint correcto
        Http::assertSent(function ($request) use ($order) {
            return $request->url() === 'https://fake-payment-gateway.free.beeceptor.com/payment-failed' &&
                   $request->method() === 'POST' &&
                   $request->data()['order_id'] === $order->id;
        });
    }

    /**
     * Test HTTP error handling
     */
    public function test_handles_http_errors(): void
    {
        Http::fake([
            'fake-payment-gateway.free.beeceptor.com/payment-success' => Http::response('Server Error', 500)
        ]);

        $order = Order::factory()->create([
            'status' => OrderStatus::PENDING,
            'amount' => 100.00
        ]);

        $response = $this->postJson("/api/orders/{$order->id}/pay");

        $response->assertStatus(201)
            ->assertJsonPath('payment.status', 'failed')
            ->assertJsonPath('order.status', OrderStatus::FAILED->value);

        // Verificar que se intentó llamar al endpoint
        Http::assertSent(function ($request) use ($order) {
            return $request->url() === 'https://fake-payment-gateway.free.beeceptor.com/payment-success' &&
                   $request->method() === 'POST';
        });
    }

    /**
     * Test network timeout handling
     */
    public function test_handles_timeout_errors(): void
    {
        Http::fake([
            'fake-payment-gateway.free.beeceptor.com/payment-success' => function () {
                throw new \Illuminate\Http\Client\ConnectionException('Connection timed out');
            }
        ]);

        $order = Order::factory()->create([
            'status' => OrderStatus::PENDING,
            'amount' => 100.00
        ]);

        $response = $this->postJson("/api/orders/{$order->id}/pay");

        $response->assertStatus(201)
            ->assertJsonPath('payment.status', 'failed')
            ->assertJsonPath('order.status', OrderStatus::FAILED->value);
    }

    /**
     * Test that payment fails when order is already paid
     */
    public function test_cannot_process_payment_for_paid_order(): void
    {
        $order = Order::factory()->create([
            'status' => OrderStatus::PAID,
            'amount' => 100.00
        ]);

        $response = $this->postJson("/api/orders/{$order->id}/pay");

        // Cambiar a 422 porque es una validación del Request
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['order']);
    }

    /**
     * Test payment retry on failed order
     */
    public function test_can_retry_payment_on_failed_order(): void
    {
        // First attempt: failed
        Http::fake([
            'fake-payment-gateway.free.beeceptor.com/payment-failed' => Http::response([
                'status' => 'declined',
                'message' => 'Insufficient funds'
            ], 200)
        ]);

        $order = Order::factory()->create([
            'status' => OrderStatus::PENDING,
            'amount' => 99.99
        ]);

        $firstResponse = $this->postJson("/api/orders/{$order->id}/pay");
        $firstResponse->assertStatus(201)
            ->assertJsonPath('order.status', OrderStatus::FAILED->value);

        // Second attempt: success - usar un monto PAR para asegurar éxito
        Http::fake([
            'fake-payment-gateway.free.beeceptor.com/payment-success' => Http::response([
                'status' => 'approved',
                'message' => 'Payment successful on retry'
            ], 200)
        ]);

        // Actualizar el monto de la orden a un valor PAR para que use el endpoint de éxito
        $order->update(['amount' => 100.00]);

        $secondResponse = $this->postJson("/api/orders/{$order->id}/pay");
        $secondResponse->assertStatus(201)
            ->assertJsonPath('order.status', OrderStatus::PAID->value);
    }

    /**
     * Test payment retry on failed order with same amount (debería seguir fallando)
     */
    public function test_retry_with_same_amount_still_fails(): void
    {
        // Both attempts should fail with same odd amount
        Http::fake([
            'fake-payment-gateway.free.beeceptor.com/payment-failed' => Http::response([
                'status' => 'declined',
                'message' => 'Insufficient funds'
            ], 200)
        ]);

        $order = Order::factory()->create([
            'status' => OrderStatus::PENDING,
            'amount' => 99.99
        ]);

        $firstResponse = $this->postJson("/api/orders/{$order->id}/pay");
        $firstResponse->assertStatus(201)
            ->assertJsonPath('order.status', OrderStatus::FAILED->value);

        $secondResponse = $this->postJson("/api/orders/{$order->id}/pay");
        $secondResponse->assertStatus(201)
            ->assertJsonPath('order.status', OrderStatus::FAILED->value);
    }
}