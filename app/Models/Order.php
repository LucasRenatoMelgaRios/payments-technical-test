<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_name',
        'amount',
        'status',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'status' => \App\Enums\OrderStatus::class, // Esto convierte automáticamente a instancia del Enum
    ];

    /**
     * Relación con los pagos del pedido
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Verificar si el pedido está pagado
     */
    public function isPaid(): bool
    {
        return $this->status === \App\Enums\OrderStatus::PAID; // Comparar con la instancia del Enum, no con ->value
    }

    /**
     * Verificar si el pedido falló
     */
    public function isFailed(): bool
    {
        return $this->status === \App\Enums\OrderStatus::FAILED; // Comparar con la instancia del Enum
    }

    /**
     * Verificar si el pedido está pendiente
     */
    public function isPending(): bool
    {
        return $this->status === \App\Enums\OrderStatus::PENDING; // Comparar con la instancia del Enum
    }

    /**
     * Obtener el número de intentos de pago
     */
    public function getPaymentAttemptsAttribute(): int
    {
        return $this->payments()->count();
    }

    /**
     * Obtener el último pago realizado
     */
    public function getLastPaymentAttribute(): ?Payment
    {
        return $this->payments()->latest()->first();
    }

    /**
     * Scope para pedidos pagados
     */
    public function scopePaid($query)
    {
        return $query->where('status', \App\Enums\OrderStatus::PAID->value); // En queries usar ->value
    }

    /**
     * Scope para pedidos pendientes
     */
    public function scopePending($query)
    {
        return $query->where('status', \App\Enums\OrderStatus::PENDING->value); // En queries usar ->value
    }

    /**
     * Scope para pedidos fallidos
     */
    public function scopeFailed($query)
    {
        return $query->where('status', \App\Enums\OrderStatus::FAILED->value); // En queries usar ->value
    }
}