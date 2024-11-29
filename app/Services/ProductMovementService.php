<?php

namespace App\Services;

use App\DTO\ProductMovementDTO;
use App\Models\ProductMovement;
use App\Models\Product;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;

class ProductMovementService
{
    /**
     * Обработка движения товара
     *
     * @param ProductMovementDTO $data
     * @return ProductMovement
     */
    public function handleMovement(ProductMovementDTO $data): ProductMovement
    {
        return DB::transaction(function () use ($data) {
            // Добавляем движение в таблицу product_movements
            $movement = ProductMovement::create([
                'product_id' => $data->product_id,
                'warehouse_id' => $data->warehouse_id,
                'quantity' => $data->quantity,
                'type' => $data->type,
                'description' => $data->description,
            ]);

            // Обновляем количество товара на складе
            $this->updateProductQuantity($data);

            return $movement;
        });
    }

    /**
     * Обновить количество товара на складе
     *
     * @param ProductMovementDTO $data
     */
    private function updateProductQuantity(ProductMovementDTO $data)
    {
        // Получаем продукт и склад
        $product = Product::find($data->product_id);
        $warehouse = Warehouse::find($data->warehouse_id);

        if (!$product || !$warehouse) {
            throw new \Exception('Product or Warehouse not found.');
        }

        // Логика изменения количества
        $currentQuantity = $product->warehouses()->where('warehouse_id', $data->warehouse_id)->value('quantity') ?? 0;

        switch ($data->type) {
            case 'arrival':
                // Поступление: увеличиваем количество товара на складе
                $product->warehouses()->updateExistingPivot($data->warehouse_id, [
                    'quantity' => $currentQuantity + $data->quantity
                ]);
                break;

            case 'sale':
                // Продажа: уменьшаем количество товара на складе
                if ($currentQuantity < $data->quantity) {
                    throw new \Exception('Not enough products in stock for the sale.');
                }
                $product->warehouses()->updateExistingPivot($data->warehouse_id, [
                    'quantity' => $currentQuantity - $data->quantity
                ]);
                break;

            case 'return':
                // Возврат: увеличиваем количество товара на складе
                $product->warehouses()->updateExistingPivot($data->warehouse_id, [
                    'quantity' => $currentQuantity + $data->quantity
                ]);
                break;

            default:
                throw new \Exception("Invalid movement type: {$data->type}");
        }
    }
}
