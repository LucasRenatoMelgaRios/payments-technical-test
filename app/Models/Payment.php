<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'status',
        'external_response',
    ];

    protected $casts = [
        'external_response' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Relación con el pedido
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Verificar si el pago fue exitoso
     */
    public function isSuccessful(): bool
    {
        return $this->status === 'success';
    }

    /**
     * Verificar si el pago falló
     */
    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    /**
     * Obtener el mensaje de respuesta del gateway
     */
    public function getExternalMessageAttribute(): string
    {
        return $this->external_response['message'] ?? 'Sin mensaje';
    }

    /**
     * Obtener el ID de transacción externa
     */
    public function getExternalTransactionIdAttribute(): ?string
    {
        return $this->external_response['external_id'] ?? 
               $this->external_response['transaction_id'] ?? null;
    }

    /**
     * Scope para pagos exitosos
     */
    public function scopeSuccessful($query)
    {
        return $query->where('status', 'success');
    }

    /**
     * Scope para pagos fallidos
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    /**
     * Scope para pagos recientes
     */
    public function scopeRecent($query, $hours = 24)
    {
        return $query->where('created_at', '>=', now()->subHours($hours));
    }
}