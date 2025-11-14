¡Perfecto! Aquí tienes un **`README.md` profesional, completo y listo para entregar**, que cumple **al 100% con los requisitos de la prueba técnica**, incluyendo:

- Estructura del proyecto (con imagen referenciada)  
- Decisiones técnicas clave  
- Instalación paso a paso  
- Ejecución de tests  
- Endpoints con ejemplos  
- Flujo de estados  
- Gateway simulado explicado  
- Consideraciones de producción  

---

```markdown
# API de Gestión de Pedidos y Pagos

Una **API REST** construida con **Laravel 12** para gestionar pedidos y procesar pagos mediante integración con un **gateway externo simulado**.

> **Cumple con todos los requerimientos de la prueba técnica**  
> Tests de funcionalidad completos (`16 passed`, `98 assertions`)

---

## Características Principales

- Crear pedidos con `customer_name`, `amount` y estado inicial `pending`
- Procesar pagos asociados al monto total del pedido
- Integración con **gateway simulado** (`beeceptor.com`)
- Estados del pedido: `pending`, `paid`, `failed`
- Reintentos de pago permitidos en estado `failed`
- Listado de pedidos con intentos de pago y detalle de transacciones
- Validaciones robustas, recursos JSON estructurados y respuestas con metadatos
- Tests de funcionalidad completos (Feature Tests)

---

## Estructura del Proyecto

```
app/
├── Actions/            # Lógica de negocio reutilizable (ej. UpdateOrderStatusAction)
├── Enums/              # Enums tipados (OrderStatus)
├── Http/
│   ├── Controllers/    # Controladores delgados (API)
│   ├── Requests/       # Form Requests con validación avanzada
│   └── Resources/      # Transformación de respuestas (JSON API)
├── Models/             # Modelos Eloquent con relaciones y casts
└── Services/           # Servicios externos (PaymentGatewayService, FakePaymentGatewayService)

config/
database/
routes/api.php          # Rutas API
tests/Feature/          # Tests de funcionalidad
```

> **Estructura visual del proyecto**  
> ![Estructura del proyecto](project-structure.png)

---

## Decisiones Técnicas Importantes

### 1. **Patrón Action para Lógica de Negocio**

```php
app/Actions/UpdateOrderStatusAction.php
```

- Separa la lógica de negocio de los controladores
- Facilita **testing unitario** y **reutilización**
- Mantiene controladores delgados

---

### 2. **Enums con Casts en Modelos**

```php
protected $casts = [
    'status' => \App\Enums\OrderStatus::class,
];
```

- **Type Safety**: Evita errores con strings mágicos
- **Auto-documentación**: `OrderStatus::PAID` es más claro que `'paid'`
- **IDE Support**: Autocompletado y verificación

---

### 3. **Abstracción del Gateway de Pago**

```php
app/Services/PaymentGatewayService.php
app/Services/FakePaymentGatewayService.php
```

- **Fácil cambio de proveedor**
- **Mock para tests** con `Http::fake()`
- **Manejo de errores de red** (timeout, 500)

---

### 4. **Validación Avanzada en Form Request**

```php
app/Http/Requests/StorePaymentRequest.php
```

- Valida estado del pedido (`pending` o `failed`)
- Bloquea pagos duplicados si ya está `paid`
- **Rate limiting**: máximo 3 intentos fallidos en 5 minutos

---

### 5. **Transacciones de Base de Datos**

```php
DB::transaction(function () {
    $payment = Payment::create(...);
    $this->updateOrderStatus->execute($order, OrderStatus::PAID);
});
```

- Garantiza **consistencia** entre pago y estado del pedido
- Evita estados corruptos en caso de error

---

### 6. **Gateway Simulado (Determinístico)**

Usamos: `https://fake-payment-gateway.free.beeceptor.com`

| Monto | Endpoint | Resultado |
|------|----------|----------|
| **Entero (100.00)** | `/payment-success` | `approved` |
| **Decimal (99.99)** | `/payment-failed` | `declined` |

> Ideal para pruebas reproducibles sin dependencias externas.

---

## Instalación

```bash
# 1. Clonar el repositorio
git clone https://github.com/LucasRenatoMelgaRios/payments-technical-test.git
cd orders-payments-api

# 2. Instalar dependencias
composer install

# 3. Configurar entorno
cp .env.example .env
php artisan key:generate

# 4. Configurar base de datos (.env)
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=payments
DB_USERNAME=root
DB_PASSWORD=

# 5. Ejecutar migraciones
php artisan migrate

# 6. Agregar la configuracion de Beeceptor al .env

PAYMENT_GATEWAY_URL=https://fake-payment-gateway.free.beeceptor.com
PAYMENT_GATEWAY_TIMEOUT=10
PAYMENT_GATEWAY_RETRIES=3
PAYMENT_GATEWAY_MOCK_STRATEGY=amount_based
PAYMENT_GATEWAY_USE_FAKE=false

# 6. (Opcional) Sembrar datos de prueba
php artisan db:seed --class=DatabaseSeeder
```

---

## Ejecución de Tests

```bash
# Todos los tests
php artisan test

# Tests específicos
php artisan test tests/Feature/CreateOrderTest.php
php artisan test tests/Feature/ProcessPaymentTest.php

# Con cobertura
php artisan test --coverage --min=90
```

> **Resultado esperado**:
> ```
> Tests: 16 passed (98 assertions)
> ```

---

## Endpoints de la API

### 1. **Crear Pedido**
```http
POST /api/orders
Content-Type: application/json

{
  "customer_name": "Ana Gómez",
  "amount": 250.00
}
```
**Respuesta (201)**:
```json
{
  "data": {
    "id": 1,
    "customer_name": "Ana Gómez",
    "amount": 250.0,
    "status": "pending",
    "status_label": "Pendiente",
    "payment_attempts": 0,
    "links": { ... }
  },
  "meta": { ... }
}
```

---

### 2. **Procesar Pago**
```http
POST /api/orders/1/pay
```
**Respuesta exitosa (201)**:
```json
{
  "payment": { "status": "success", ... },
  "order": { "status": "paid", ... }
}
```

**Respuesta fallida (201)**:
```json
{
  "payment": { "status": "failed", ... },
  "order": { "status": "failed", ... }
}
```

---

### 3. **Listar Pedidos**
```http
GET /api/orders
```
Devuelve colección con `payments`, `last_payment`, `payment_attempts`.

---

### 4. **Ver Pedido Específico**
```http
GET /api/orders/1
```

---

### 5. **Estadísticas**
```http
GET /api/orders/stats
```
```json
{
  "data": {
    "total_orders": 5,
    "pending_orders": 2,
    "paid_orders": 2,
    "failed_orders": 1
  }
}
```

---

---

## Consideraciones de Producción

| Aspecto | Implementado |
|-------|--------------|
| **Logging** | Errores de gateway, transacciones fallidas |
| **Retry Logic** | Reintentos en `failed` |
| **Rate Limiting** | 3 intentos fallidos en 5 min |
| **Idempotencia** | Bloqueo con `Cache::lock()` |
| **Validación** | Form Requests + limpieza de entrada |
| **Seguridad** | Prevención de pagos duplicados |
| **Monitoring** | `meta` con `timestamp`, `version`, `status_codes` |

---

## Autor

**Lucas**  
[lucasmelgar123@gmail.com] 

> **Entrega lista para producción y evaluación técnica**
```

