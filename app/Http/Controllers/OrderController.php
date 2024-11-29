<?php

namespace App\Http\Controllers;

use App\DTO\OrderDTO;
use App\Http\Requests\StoreOrderRequest;
use App\Http\Requests\UpdateOrderRequest;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class OrderController extends Controller
{
    protected $orderService;

    public function __construct(OrderService $orderService)
    {
        $this->orderService = $orderService;
    }

    public function index(Request $request)
    {
        $query = Order::with(['orderItems.product', 'warehouse']);

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->has('customer')) {
            $query->where('customer', 'LIKE', '%' . $request->input('customer') . '%');
        }

        if ($request->has('warehouse_id')) {
            $query->where('warehouse_id', $request->input('warehouse_id'));
        }

        $perPage = $request->input('per_page', 15);
        return $query->paginate($perPage);
    }

    public function store(StoreOrderRequest $request)
    {
        // Получаем данные после валидации из StoreOrderRequest
        $validatedData = $request->validated();

        // Создаем DTO для заказа
        $orderDTO = new OrderDTO(
            $validatedData['customer'],
            $validatedData['warehouse_id'],
            $validatedData['items']
        );

        try {
            $order = $this->orderService->createOrder($orderDTO);
            return response()->json($order, 201);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function update(UpdateOrderRequest $request, $orderId)
    {
        $validatedData = $request->validated();

        $orderDTO = new OrderDTO(
            $validatedData['customer'],
            $validatedData['warehouse_id'], // Обновление warehouse_id при необходимости
            $validatedData['items']
        );

        try {
            $order = $this->orderService->updateOrder($orderId, $orderDTO);
            return response()->json($order);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function complete($orderId)
    {
        try {
            $order = $this->orderService->completeOrder($orderId);
            return response()->json($order);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function cancel($orderId)
    {
        try {
            $order = $this->orderService->cancelOrder($orderId);
            return response()->json($order);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function reopen($orderId)
    {
        try {
            $order = $this->orderService->reopenOrder($orderId);
            return response()->json($order);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }
}
