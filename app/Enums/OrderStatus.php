<?php

namespace App\Enums;

enum OrderStatus: string
{
    case PENDING = 'pending';
    case PAID = 'paid';
    case FAILED = 'failed';

    /**
     * Obtener la etiqueta legible para humanos
     */
    public function label(): string
    {
        return match($this) {
            self::PENDING => 'Pendiente',
            self::PAID => 'Pagado',
            self::FAILED => 'Fallido',
        };
    }

    /**
     * Obtener todos los valores como array
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Obtener todos los casos como array [value => label]
     */
    public static function toArray(): array
    {
        $array = [];
        foreach (self::cases() as $case) {
            $array[$case->value] = $case->label();
        }
        return $array;
    }

    /**
     * Verificar si un estado es válido
     */
    public static function isValid(string $status): bool
    {
        return in_array($status, self::values());
    }
}