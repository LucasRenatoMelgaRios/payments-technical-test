<?php

namespace App\Http\Resources;

use App\Enums\OrderStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
public function toArray(Request $request): array
{
    return [
        'id' => $this->id,
        'customer_name' => $this->customer_name,
        'amount' => (float) $this->amount,
        'status' => $this->status->value,
        'status_label' => $this->status->label(),
        'payment_attempts' => $this->payment_attempts,
        'created_at' => $this->created_at->toISOString(),
        'updated_at' => $this->updated_at->toISOString(),

        'payments' => PaymentResource::collection($this->whenLoaded('payments')),
        'last_payment' => new PaymentResource($this->whenLoaded('lastPayment')),

        'links' => [
            'self' => route('orders.show', $this->id),
            'payments' => route('orders.payments.index', $this->id),
        ],
    ];
}


    /**
     * Customize the outgoing response for the resource.
     */
    public function with(Request $request): array
    {
        return [
            'meta' => [
                'timestamp' => now()->toISOString(),
                'version' => '1.0.0',
                'status_codes' => OrderStatus::toArray(),
            ],
        ];
    }
}