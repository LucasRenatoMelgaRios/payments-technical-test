<?php

namespace App\Http\Controllers;

use App\Actions\CreateOrderAction;
use App\Http\Requests\StoreOrderRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Log;

class OrderController extends Controller
{
    /**
     * Listar todos los pedidos
     */
    public function index(): AnonymousResourceCollection
    {
        Log::info('Fetching all orders');
        
        $orders = Order::withCount('payments')
            ->with(['payments' => function ($query) {
                $query->latest()->limit(10); // Últimos 10 pagos
            }])
            ->latest()
            ->paginate(20);

        Log::info('Orders fetched successfully', [
            'total' => $orders->total(),
            'count' => $orders->count()
        ]);

        return OrderResource::collection($orders);
    }

    /**
     * Crear un nuevo pedido
     */
    public function store(StoreOrderRequest $request, CreateOrderAction $createOrder): OrderResource|JsonResponse
    {
        try {
            Log::info('Creating new order', [
                'customer_name' => $request->customer_name,
                'amount' => $request->amount
            ]);

            $order = $createOrder->execute($request->validated());

            Log::info('Order created successfully', [
                'order_id' => $order->id,
                'status' => $order->status
            ]);

            return new OrderResource($order);

        } catch (\Exception $e) {
            Log::error('Error creating order', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'customer_name' => $request->customer_name,
                'amount' => $request->amount
            ]);

            return response()->json([
                'message' => 'Error al crear el pedido',
                'error' => config('app.debug') ? $e->getMessage() : 'Error interno del servidor'
            ], 500);
        }
    }

    /**
     * Mostrar un pedido específico
     */
    public function show($id): OrderResource|JsonResponse
    {
        try {
            Log::info('Fetching order', ['order_id' => $id]);

            // Buscar la orden y manejar automáticamente 404 si no existe
            $order = Order::with(['payments' => function ($query) {
                $query->latest();
            }])->findOrFail($id);

            return new OrderResource($order);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            Log::warning('Order not found', [
                'order_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'message' => 'Pedido no encontrado',
                'error' => 'El pedido solicitado no existe en el sistema',
                'order_id' => $id
            ], 404);

        } catch (\Exception $e) {
            Log::error('Error fetching order', [
                'order_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'message' => 'Error al obtener el pedido',
                'error' => config('app.debug') ? $e->getMessage() : 'Error interno del servidor'
            ], 500);
        }
    }

    /**
     * Obtener estadísticas de pedidos
     */
    public function stats(): JsonResponse
    {
        try {
            $stats = [
                'total_orders' => Order::count(),
                'pending_orders' => Order::pending()->count(),
                'paid_orders' => Order::paid()->count(),
                'failed_orders' => Order::failed()->count(),
                'total_revenue' => Order::paid()->sum('amount'),
                'average_order_value' => Order::paid()->avg('amount') ?? 0,
            ];

            Log::info('Order stats fetched', $stats);

            return response()->json([
                'data' => $stats,
                'timestamp' => now()->toISOString()
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching order stats', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'message' => 'Error al obtener estadísticas',
                'error' => config('app.debug') ? $e->getMessage() : 'Error interno del servidor'
            ], 500);
        }
    }
}