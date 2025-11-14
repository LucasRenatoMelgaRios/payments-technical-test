<?php

namespace Database\Factories;

use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

class PaymentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'status' => $this->faker->randomElement(['success', 'failed']),
            'external_response' => [
                'success' => $this->faker->boolean(),
                'message' => $this->faker->sentence(),
                'external_id' => 'txn_' . $this->faker->uuid(),
                'gateway_status' => $this->faker->randomElement(['approved', 'declined', 'pending']),
                'data' => [
                    'status' => $this->faker->randomElement(['approved', 'declined']),
                    'message' => $this->faker->sentence(),
                    'transaction_id' => 'txn_' . $this->faker->uuid(),
                ],
            ],
            'created_at' => $this->faker->dateTimeBetween('-30 days', 'now'),
            'updated_at' => $this->faker->dateTimeBetween('-30 days', 'now'),
        ];
    }

    public function successful(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'success',
            'external_response' => [
                'success' => true,
                'message' => 'Payment processed successfully',
                'external_id' => 'txn_' . $this->faker->uuid(),
                'gateway_status' => 'approved',
                'data' => [
                    'status' => 'approved',
                    'message' => 'Payment processed successfully',
                    'transaction_id' => 'txn_' . $this->faker->uuid(),
                    'amount_charged' => $this->faker->randomFloat(2, 10, 1000),
                    'currency' => 'USD',
                    'timestamp' => now()->toISOString(),
                ],
                'http_status' => 200,
            ],
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
            'external_response' => [
                'success' => false,
                'message' => 'Insufficient funds',
                'external_id' => null,
                'gateway_status' => 'declined',
                'data' => [
                    'status' => 'declined',
                    'message' => 'Insufficient funds',
                    'error_code' => 'INSUFFICIENT_FUNDS',
                    'timestamp' => now()->toISOString(),
                ],
                'http_status' => 200,
            ],
        ]);
    }

    public function forOrder(Order $order): static
    {
        return $this->state(fn (array $attributes) => [
            'order_id' => $order->id,
        ]);
    }

    public function withGatewayResponse(array $response): static
    {
        return $this->state(fn (array $attributes) => [
            'external_response' => $response,
        ]);
    }
}