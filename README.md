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

---

## Decisiones Técnicas Importantes

### 1. Patrón Action para la Lógica de Negocio

Para mantener los controladores limpios y enfocados, decidí mover toda la lógica de negocio a acciones (`app/Actions`). Por ejemplo, `UpdateOrderStatusAction.php` se encarga únicamente de actualizar el estado de un pedido según el resultado del pago.  

Esto me ayudó mucho a:  

- Testear la lógica de forma aislada sin depender del controlador.  
- Reutilizar la misma acción en distintos lugares si surgía la necesidad.  
- Mantener los controladores delgados y más legibles.  

Al principio fue un poco complicado separar todo correctamente y pasar los datos entre acciones, pero ahora el código es mucho más mantenible y fácil de entender.

---

### 2. Enums con Casts en Modelos

Decidí usar Enums para los estados del pedido (`pending`, `paid`, `failed`) y castear automáticamente en el modelo.  

Ventajas que obtuve:  

- Evito errores por “strings mágicos” en el código.  
- El IDE ayuda con autocompletado y validaciones de tipo.  
- Hace el código más legible y auto-documentado: `OrderStatus::PAID` es más claro que `'paid'`.  

Actualizar todas las partes del código que antes usaban strings directos fue un reto, pero asegura consistencia en todo el proyecto.

---

### 3. Abstracción del Gateway de Pago

Para simular el procesamiento de pagos, creé un servicio de pagos (`PaymentGatewayService`) y una versión fake (`FakePaymentGatewayService`) para pruebas.  

Esto me permitió:  

- Cambiar de proveedor de pago sin tocar la lógica de negocio.  
- Testear escenarios de éxito y fallo con `Http::fake()` sin depender de APIs externas.  
- Manejar errores de red y timeouts de forma centralizada.  

Al principio algunas pruebas fallaban de manera aleatoria si el endpoint real estaba lento, y la abstracción resolvió ese problema.

---

### 4. Validación Avanzada en Form Request

En el `StorePaymentRequest` agregué reglas para:  

- Permitir pagos solo si el pedido está `pending` o `failed`.  
- Bloquear intentos duplicados si el pedido ya está `paid`.  
- Limitar los reintentos a 3 pagos fallidos en 5 minutos.  

Esto fue un reto porque tuve que pensar en varios escenarios de fallo y reintento, pero ahora el sistema es más robusto y seguro.

---

### 5. Transacciones de Base de Datos

Decidí envolver la creación de pagos y la actualización del estado del pedido en transacciones (`DB::transaction`).  

- Garantiza que si algo falla al registrar un pago, el estado del pedido no quede inconsistente.  
- Evita que queden pedidos “pagados parcialmente” o duplicados.  

Al principio me costó identificar todos los puntos que debían estar dentro de la transacción, pero ahora tengo confianza en la consistencia de los datos.

---

### 6. Gateway Simulado (Determinístico)

Para pruebas reproducibles sin depender de servicios externos, usé un gateway simulado en Beeceptor (`https://fake-payment-gateway.free.beeceptor.com`).  

Configuré reglas claras:  

| Monto            | Endpoint           | Resultado  |
|-----------------|-----------------|-----------|
| Entero (100.00)  | `/payment-success` | `approved` |
| Decimal (99.99)  | `/payment-failed`  | `declined` |

Esto me permitió probar todos los escenarios de pago de manera controlada. Antes de esto, las pruebas eran inestables porque los endpoints externos podían fallar o devolver datos inconsistentes.

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

Endpoint para obtener todos los detalles de un pedido incluyendo su estado, intentos de pago y los pagos asociados.

**Ejemplo de Respuesta (200)**:

```json
{
  "data": {
    "id": 12,
    "customer_name": "Juan Pérez",
    "amount": 99,
    "status": "paid",
    "status_label": "Pagado",
    "payment_attempts": 1,
    "created_at": "2025-11-14T03:31:41.000000Z",
    "updated_at": "2025-11-14T05:16:26.000000Z",
    "payments": [
      {
        "id": 21,
        "order_id": 12,
        "status": "success",
        "status_label": "Exitoso",
        "external_transaction_id": "txn_success_1234",
        "external_message": "Payment processed successfully",
        "created_at": "2025-11-14T05:16:26.000000Z",
        "updated_at": "2025-11-14T05:16:26.000000Z",
        "links": {
          "order": "http://localhost:8000/api/orders/12"
        }
      }
    ],
    "links": {
      "self": "http://localhost:8000/api/orders/12",
      "payments": "http://localhost:8000/api/orders/12/payments"
    }
  },
  "meta": {
    "timestamp": "2025-11-14T05:35:49.909479Z",
    "version": "1.0.0",
    "status_codes": {
      "pending": "Pendiente",
      "paid": "Pagado",
      "failed": "Fallido"
    }
  }
}
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

## Consideraciones futuras 

| Aspecto | Implementado |
|-------|--------------|
| **Logging** | Errores de gateway, transacciones fallidas |
| **Retry Logic** | Reintentos en `failed` |
| **Idempotencia** | Bloqueo con `Cache::lock()` |

---
 
## Tests de Funcionalidad

La API incluye tests de funcionalidad (Feature Tests) que permiten validar el correcto funcionamiento de los endpoints y flujos principales del sistema de pedidos y pagos. Estos tests aseguran que la aplicación cumpla con los requisitos de negocio definidos y ayudan a prevenir errores al hacer cambios futuros.

### Tipos de Tests Implementados

1. **Creación de Pedidos (`CreateOrderTest`)**
   - Verifica que un pedido se pueda crear correctamente con:
     - Nombre del cliente
     - Monto total
     - Estado inicial `pending`
   - Confirma que la respuesta devuelva la estructura esperada (`OrderResource`) y que el pedido quede registrado en la base de datos.

2. **Procesamiento de Pagos (`ProcessPaymentTest`)**
   - Simula pagos a través de un gateway externo mockeado.
   - Casos testeados:
     - Pago exitoso: el pedido cambia a estado `paid`.
     - Pago fallido: el pedido cambia a estado `failed`.
     - Reintentos de pago para pedidos fallidos.
   - Verifica que se registren los pagos asociados al pedido y se actualicen correctamente los contadores de intentos.

3. **Listar Pedidos y Ver Pedido Específico**
   - Comprueba que los endpoints `GET /api/orders` y `GET /api/orders/{id}` devuelvan:
     - Estado actual del pedido
     - Número de intentos de pago
     - Pagos asociados
   - Garantiza que los datos se estructuren correctamente en la respuesta (`OrderResource` y `PaymentResource`).

### Ejecución de Tests

Para correr todos los tests:

```bash
php artisan test
```

Para ejecutar un test específico:

```bash
php artisan test tests/Feature/CreateOrderTest.php
php artisan test tests/Feature/ProcessPaymentTest.php
```

Para ver cobertura de código:

```bash
php artisan test --coverage
```

### Beneficios de los Tests

* Aseguran que la lógica de negocio funciona correctamente.
* Previenen regresiones al hacer cambios en la API.
* Permiten simular escenarios de pago exitosos y fallidos sin depender de un gateway real.
* Validan la integridad de datos entre pedidos y pagos.


## Autor

**Lucas**  
[lucasmelgar123@gmail.com] 

> **Entrega lista para producción y evaluación técnica**

