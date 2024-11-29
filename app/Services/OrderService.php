<?php

namespace App\Services;

use App\DTO\OrderDTO;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductMovement;
use App\Models\Stock;
use Illuminate\Support\Facades\DB;
use App\Exceptions\StockException;
use Illuminate\Support\Facades\Log;

class OrderService
{
    /**
     * Создание заказа с резервированием товаров
     */
    public function createOrder(OrderDTO $orderDTO)
    {
        return DB::transaction(function () use ($orderDTO) {
            try {
                // Проверка наличия товаров на складе
                $this->checkStockAvailability($orderDTO->getWarehouseId(), $orderDTO->getItems());

                // Создание заказа
                $order = Order::create([
                    'customer' => $orderDTO->getCustomer(),
                    'warehouse_id' => $orderDTO->getWarehouseId(),
                    'status' => 'active'
                ]);

                if (!$order) {
                    throw new \Exception("Failed to create order.");
                }

                Log::info("Order created successfully", ['order_id' => $order->id]);

                // Создание позиций заказа и списание товаров
                foreach ($orderDTO->getItems() as $item) {
                    $orderItem = OrderItem::create([
                        'order_id' => $order->id,
                        'product_id' => $item->getProductId(),
                        'count' => $item->getCount()
                    ]);

                    if (!$orderItem) {
                        throw new \Exception("Failed to create order item for product ID: " . $item->getProductId());
                    }

                    // Проверка и списание товара со склада
                    $stockReduced = $this->reduceStock($orderDTO->getWarehouseId(), $item->getProductId(), $item->getCount());

                    if (!$stockReduced) {
                        throw new \Exception("Failed to reduce stock for product ID: " . $item->getProductId());
                    }

                    Log::info("Order item created and stock reduced", [
                        'order_id' => $order->id,
                        'product_id' => $item->getProductId(),
                        'count' => $item->getCount()
                    ]);
                }

                return $order;
            } catch (\Exception $e) {
                // Логирование ошибки
                Log::error('Error creating order: ' . $e->getMessage(), [
                    'exception' => $e
                ]);

                // Проброс исключения для отката транзакции
                throw $e;
            }
        });
    }

    /**
     * Обновление заказа
     */
    public function updateOrder($orderId, OrderDTO $orderDTO)
    {
        return DB::transaction(function () use ($orderId, $orderDTO) {
            $order = Order::findOrFail($orderId);

            if ($order->status !== 'active') {
                throw new \Exception('Заказ не может быть изменен');
            }

            // Сначала проверяем наличие товаров на складе, чтобы избежать изменений на складе после возвращения товаров
            $this->checkStockAvailability($order->warehouse_id, $orderDTO->getItems());

            // Возвращаем товары на склад, если заказ изменяется
            $this->returnOrderItemsToStock($order);

            // Очищаем старые позиции
            $order->orderItems()->delete();

            // Обновляем заказ
            $order->update(['customer' => $orderDTO->getCustomer()]);

            // Добавляем новые позиции и списываем товары
            foreach ($orderDTO->getItems() as $item) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $item->getProductId(),
                    'count' => $item->getCount()
                ]);
                $this->reduceStock($order->warehouse_id, $item->getProductId(), $item->getCount());
            }

            return $order;
        });
    }

    /**
     * Завершение заказа
     */
    public function completeOrder($orderId)
    {
        return DB::transaction(function () use ($orderId) {
            $order = Order::findOrFail($orderId);

            if ($order->status !== 'active') {
                throw new \Exception('Заказ не может быть завершен');
            }

            $order->update([
                'status' => 'completed',
                'completed_at' => now()
            ]);

            return $order;
        });
    }

    /**
     * Отмена заказа
     */
    public function cancelOrder($orderId)
    {
        return DB::transaction(function () use ($orderId) {
            $order = Order::findOrFail($orderId);

            if ($order->status !== 'active') {
                throw new \Exception('Заказ не может быть отменен');
            }

            // Возвращаем товары на склад
            $this->returnOrderItemsToStock($order);

            // Обновляем статус заказа
            $order->update(['status' => 'canceled']);

            return $order;
        });
    }

    /**
     * Возобновление заказа
     */
    public function reopenOrder($orderId)
    {
        return DB::transaction(function () use ($orderId) {
            $order = Order::findOrFail($orderId);

            if ($order->status !== 'canceled') {
                throw new \Exception('Заказ не может быть возобновлен');
            }

            // Проверяем наличие товаров на складе перед возобновлением
            $this->checkOrderItemsStockAvailability($order);

            // Списываем товары, если заказ возобновляется
            foreach ($order->orderItems as $item) {
                $this->reduceStock($order->warehouse_id, $item->product_id, $item->count);
            }

            $order->update(['status' => 'active']);

            return $order;
        });
    }

    /**
     * Проверка наличия товаров на складе
     */
    private function checkStockAvailability($warehouseId, $items)
    {
        foreach ($items as $item) {
            $productId = $item['product_id'];
            $count = $item['count'];

            // Получаем текущий склад для товара
            $stock = Stock::where('warehouse_id', $warehouseId)
                ->where('product_id', $productId)
                ->first();

            if (!$stock || $stock->stock < $count) {
                // Логирование и выброс исключения
                Log::error('Stock availability check failed', [
                    'product_id' => $productId,
                    'required_count' => $count,
                    'available_stock' => $stock ? $stock->stock : 0
                ]);
                throw new StockException("Недостаточно товара {$productId} на складе");
            }
        }
    }

    /**
     * Списание товаров со склада
     */
    private function reduceStock($warehouseId, $productId, $quantity)
    {
        $stock = Stock::where('warehouse_id', $warehouseId)
            ->where('product_id', $productId)
            ->firstOrFail();

        if ($stock->stock < $quantity) {
            Log::error('Not enough stock to reduce', [
                'product_id' => $productId,
                'requested' => $quantity,
                'available' => $stock->stock
            ]);
            return false; // Недостаточно товара
        }

        // Снижаем количество на складе
        $stock->decrement('stock', $quantity);

        // Создаем движение товара
        $this->createProductMovement($warehouseId, $productId, -$quantity, 'Списание товара по заказу');

        return true;
    }

    /**
     * Возврат товаров на склад
     */
    private function returnOrderItemsToStock($order)
    {
        foreach ($order->orderItems as $item) {
            $stock = Stock::where('warehouse_id', $order->warehouse_id)
                ->where('product_id', $item->product_id)
                ->firstOrFail();

            // Возвращаем товары на склад
            $stock->increment('stock', $item->count);

            // Создаем движение товара
            $this->createProductMovement($order->warehouse_id, $item->product_id, $item->count, 'Возврат товара при отмене заказа');
        }
    }

    /**
     * Проверка возможности возобновления заказа
     */
    private function checkOrderItemsStockAvailability($order)
    {
        foreach ($order->orderItems as $item) {
            $stock = Stock::where('warehouse_id', $order->warehouse_id)
                ->where('product_id', $item->product_id)
                ->firstOrFail();

            if ($stock->stock < $item->count) {
                throw new StockException("Недостаточно товара {$item->product_id} на складе для возобновления заказа");
            }
        }
    }

    /**
     * Создание движения товара
     */
    private function createProductMovement($warehouseId, $productId, $quantity, $reason)
    {
        ProductMovement::create([
            'warehouse_id' => $warehouseId,
            'product_id' => $productId,
            'quantity' => $quantity,
            'reason' => $reason
        ]);
    }
}
