<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class CreateOrderTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    /**
     * Test creating a valid order
     */
    public function test_can_create_order_with_valid_data(): void
    {
        Log::info('Starting test: test_can_create_order_with_valid_data');
        
        $data = [
            'customer_name' => 'John Doe',
            'amount' => 199.99,
        ];

        $response = $this->postJson('/api/orders', $data);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'customer_name',
                    'amount',
                    'status',
                    'status_label',
                    'payment_attempts',
                    'created_at',
                    'updated_at',
                    'links',
                ],
                'meta' => [
                    'timestamp',
                    'version',
                    'status_codes',
                ],
            ])
            ->assertJson([
                'data' => [
                    'customer_name' => 'John Doe',
                    'amount' => 199.99,
                    'status' => OrderStatus::PENDING->value,
                    'status_label' => 'Pendiente',
                    'payment_attempts' => 0,
                ]
            ]);

        $this->assertDatabaseHas('orders', [
            'customer_name' => 'John Doe',
            'amount' => 199.99,
            'status' => OrderStatus::PENDING->value,
        ]);

        Log::info('Order created successfully in test');
    }

    /**
     * Test validation for required fields
     */
    public function test_cannot_create_order_without_required_fields(): void
    {
        $response = $this->postJson('/api/orders', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['customer_name', 'amount']);
    }

    /**
     * Test validation for customer name
     */
    public function test_cannot_create_order_with_invalid_customer_name(): void
    {
        $testCases = [
            ['name' => '', 'message' => 'empty name'],
            ['name' => 'A', 'message' => 'too short name'],
            ['name' => str_repeat('A', 101), 'message' => 'too long name'],
            ['name' => 'John123', 'message' => 'name with numbers'],
            ['name' => 'John@Doe', 'message' => 'name with special chars'],
        ];

        foreach ($testCases as $case) {
            $response = $this->postJson('/api/orders', [
                'customer_name' => $case['name'],
                'amount' => 100.00,
            ]);

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['customer_name']);
        }
    }

    /**
     * Test validation for amount
     */
    public function test_cannot_create_order_with_invalid_amount(): void
    {
        $testCases = [
            ['amount' => 0, 'message' => 'zero amount'],
            ['amount' => -100, 'message' => 'negative amount'],
            ['amount' => 1000000, 'message' => 'too large amount'],
            ['amount' => 'invalid', 'message' => 'non-numeric amount'],
            ['amount' => 100.123, 'message' => 'too many decimals'],
        ];

        foreach ($testCases as $case) {
            $response = $this->postJson('/api/orders', [
                'customer_name' => 'John Doe',
                'amount' => $case['amount'],
            ]);

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['amount']);
        }
    }

    /**
     * Test amount formatting and cleaning
     */
    public function test_amount_formatting_and_cleaning(): void
    {
        $testCases = [
            ['input' => '100,50', 'expected' => 100.50],
            ['input' => ' 100.50 ', 'expected' => 100.50],
            ['input' => '$100.50', 'expected' => 100.50],
        ];

        foreach ($testCases as $case) {
            $response = $this->postJson('/api/orders', [
                'customer_name' => 'John Doe',
                'amount' => $case['input'],
            ]);

            $response->assertStatus(201)
                ->assertJsonPath('data.amount', $case['expected']);
        }
    }

    /**
     * Test customer name formatting
     */
    public function test_customer_name_formatting(): void
    {
        $testCases = [
            ['input' => 'john doe', 'expected' => 'John Doe'],
            ['input' => 'JOHN DOE', 'expected' => 'John Doe'],
            ['input' => '  john   doe  ', 'expected' => 'John Doe'],
        ];

        foreach ($testCases as $case) {
            $response = $this->postJson('/api/orders', [
                'customer_name' => $case['input'],
                'amount' => 100.00,
            ]);

            $response->assertStatus(201)
                ->assertJsonPath('data.customer_name', $case['expected']);
        }
    }

    /**
     * Test creating multiple orders
     */
    public function test_can_create_multiple_orders(): void
    {
        $orders = [
            ['customer_name' => 'Alice Smith', 'amount' => 150.00],
            ['customer_name' => 'Bob Johnson', 'amount' => 250.50],
            ['customer_name' => 'Carol Williams', 'amount' => 99.99],
        ];

        foreach ($orders as $order) {
            $response = $this->postJson('/api/orders', $order);
            $response->assertStatus(201);
        }

        $this->assertDatabaseCount('orders', 3);
        
        $statsResponse = $this->getJson('/api/orders/stats');
        $statsResponse->assertStatus(200)
            ->assertJsonPath('data.total_orders', 3)
            ->assertJsonPath('data.pending_orders', 3);
    }
}