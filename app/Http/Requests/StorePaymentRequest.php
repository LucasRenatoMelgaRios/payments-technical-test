<?php

namespace App\Http\Requests;

use App\Models\Order;
use App\Enums\OrderStatus;
use Illuminate\Foundation\Http\FormRequest;

class StorePaymentRequest extends FormRequest
{
    /**
     * Determinar si el usuario está autorizado
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Reglas de validación
     */
    public function rules(): array
    {
        return [
            // No se requieren campos adicionales para procesar pago
        ];
    }

    /**
     * Reglas de validación condicionales basadas en el pedido
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $order = $this->route('order');

            // Laravel ya maneja el 404 si el order no existe
            // Solo necesitamos validar el estado del pedido
            $this->validateOrderForPayment($validator, $order);
        });
    }

    /**
     * Validar que el pedido puede recibir pagos
     */
private function validateOrderForPayment($validator, Order $order): void
{
    $order = $order->fresh(); // <-- fuerza casts

    if ($order->isPaid()) {
        $validator->errors()->add(
            'order',
            'Este pedido ya ha sido pagado y no acepta nuevos pagos.'
        );
        return;
    }

    $allowed = [OrderStatus::PENDING->value, OrderStatus::FAILED->value];

    if (!in_array($order->status->value ?? $order->status, $allowed)) {
        $validator->errors()->add(
            'order',
            'El pedido no está en un estado que permita procesar pagos.'
        );
        return;
    }

    if ($order->amount <= 0) {
        $validator->errors()->add('amount', 'El monto del pedido debe ser mayor a 0.');
        return;
    }

    $recentFailedAttempts = $order->payments()
        ->failed()
        ->where('created_at', '>=', now()->subMinutes(5))
        ->count();

    if ($recentFailedAttempts >= 3) {
        $validator->errors()->add(
            'order',
            'Demasiados intentos fallidos recientemente. Por favor espere 5 minutos antes de intentar nuevamente.'
        );
    }
}

    /**
     * Mensajes de error personalizados
     */
    public function messages(): array
    {
        return [
            'order.paid' => 'El pedido ya ha sido pagado.',
            'order.invalid_state' => 'El pedido no está en un estado que permita procesar pagos.',
            'amount.positive' => 'El monto del pedido debe ser positivo.',
            'order.too_many_attempts' => 'Demasiados intentos fallidos recientemente.',
        ];
    }
}