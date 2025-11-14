<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOrderRequest extends FormRequest
{
    /**
     * Determinar si el usuario está autorizado
     */
    public function authorize(): bool
    {
        return true; // No hay autenticación requerida
    }

    /**
     * Reglas de validación
     */
    public function rules(): array
    {
        return [
            'customer_name' => [
                'required',
                'string',
                'min:2',
                'max:100',
                'regex:/^[\pL\s\-\.]+$/u', // Solo letras, espacios, guiones y puntos
            ],
            'amount' => [
                'required',
                'numeric',
                'min:0.01',
                'max:999999.99',
                'regex:/^\d+(\.\d{1,2})?$/', // Formato decimal válido
            ],
        ];
    }

    /**
     * Mensajes de error personalizados
     */
    public function messages(): array
    {
        return [
            'customer_name.required' => 'El nombre del cliente es obligatorio.',
            'customer_name.string' => 'El nombre debe ser una cadena de texto.',
            'customer_name.min' => 'El nombre debe tener al menos 2 caracteres.',
            'customer_name.max' => 'El nombre no puede exceder los 100 caracteres.',
            'customer_name.regex' => 'El nombre solo puede contener letras, espacios, guiones y puntos.',

            'amount.required' => 'El monto es obligatorio.',
            'amount.numeric' => 'El monto debe ser un valor numérico.',
            'amount.min' => 'El monto debe ser al menos 0.01.',
            'amount.max' => 'El monto no puede exceder 999,999.99.',
            'amount.regex' => 'El monto debe tener un formato decimal válido (ej: 100.50).',
        ];
    }

    /**
     * Atributos personalizados
     */
    public function attributes(): array
    {
        return [
            'customer_name' => 'nombre del cliente',
            'amount' => 'monto',
        ];
    }

    /**
     * Preparar datos para validación
     */
    protected function prepareForValidation(): void
    {
        // Limpiar y formatear datos antes de validar
        if ($this->has('customer_name')) {
            $this->merge([
                'customer_name' => trim(preg_replace('/\s+/', ' ', $this->customer_name)),
            ]);
        }

        if ($this->has('amount')) {
            // Asegurar que el amount sea numérico
            $amount = $this->amount;
            if (is_string($amount)) {
                $amount = str_replace(',', '.', $amount); // Manejar formato europeo
                $amount = preg_replace('/[^\d\.]/', '', $amount); // Remover caracteres no numéricos
            }
            
            $this->merge([
                'amount' => (float) $amount,
            ]);
        }
    }

    /**
     * Reglas condicionales
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Validación adicional para montos específicos
            if ($this->amount && $this->amount < 0.01) {
                $validator->errors()->add(
                    'amount', 
                    'El monto debe ser mayor a 0.'
                );
            }

            // Validar que el monto no sea excesivamente grande para prevenir errores
            if ($this->amount && $this->amount > 999999.99) {
                $validator->errors()->add(
                    'amount',
                    'El monto es demasiado grande. Por favor contacte al administrador.'
                );
            }
        });
    }

    /**
     * Datos validados con formato adicional
     */
    public function validated($key = null, $default = null)
    {
        $validated = parent::validated($key, $default);

        // Asegurar formato consistente
        $validated['amount'] = round($validated['amount'], 2);
        $validated['customer_name'] = ucwords(strtolower($validated['customer_name']));

        return $validated;
    }
}