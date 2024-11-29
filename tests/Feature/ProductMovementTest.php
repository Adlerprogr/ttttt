<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Warehouse;
use App\Models\Stock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class ProductMovementTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Подготовка данных для тестов.
     *
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();

        // Заполняем базу тестовыми данными
        Artisan::call('data:populate');
    }

    public function test_add_product_movement()
    {
        // Создаем продукт и склад для теста
        $product = Product::first();
        $warehouse = Warehouse::first();

        // Проверяем начальные остатки товара на складе
        $initialStock = Stock::where('product_id', $product->id)
            ->where('warehouse_id', $warehouse->id)
            ->first()
            ->stock;

        // Добавляем движение (поступление)
        $response = $this->postJson('/product-movements', [
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => 10,
            'type' => 'arrival',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('product_movements', [
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => 10,
            'type' => 'arrival',
        ]);

        // Проверяем остатки после поступления
        $newStock = Stock::where('product_id', $product->id)
            ->where('warehouse_id', $warehouse->id)
            ->first()
            ->stock;

        $this->assertEquals($initialStock + 10, $newStock);
    }

    public function test_sale_product_movement()
    {
        // Создаем продукт и склад для теста
        $product = Product::first();
        $warehouse = Warehouse::first();

        // Добавляем поступление (arrivals)
        $this->postJson('/product-movements', [
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => 20,
            'type' => 'arrival',
        ]);

        // Проверяем начальные остатки товара на складе после поступления
        $initialStock = Stock::where('product_id', $product->id)
            ->where('warehouse_id', $warehouse->id)
            ->first()
            ->stock;

        // Добавляем продажу (sale)
        $response = $this->postJson('/product-movements', [
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => 5,
            'type' => 'sale',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('product_movements', [
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => 5,
            'type' => 'sale',
        ]);

        // Проверяем остатки после продажи
        $newStock = Stock::where('product_id', $product->id)
            ->where('warehouse_id', $warehouse->id)
            ->first()
            ->stock;

        $this->assertEquals($initialStock + 20 - 5, $newStock);
    }
}
