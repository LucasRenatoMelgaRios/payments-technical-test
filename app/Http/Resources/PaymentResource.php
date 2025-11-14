<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
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
            'order_id' => $this->order_id,
            'status' => $this->status,
            'status_label' => $this->isSuccessful() ? 'Exitoso' : 'Fallido',
            'external_transaction_id' => $this->external_transaction_id,
            'external_message' => $this->external_message,
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
            
            // Gateway response details
            'gateway_response' => $this->when(
                $request->user() && $request->user()->isAdmin(), // Ejemplo de condición
                $this->external_response
            ),
            
            // Metadata
            'links' => [
                'order' => route('orders.show', $this->order_id),
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
            ],
        ];
    }
}