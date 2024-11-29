<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Product;
use App\Models\Warehouse;
use App\Models\Stock;
use Illuminate\Support\Facades\DB;

class PopulateTestData extends Command
{
    protected $signature = 'data:populate';
    protected $description = 'Populate database with test data';

    public function handle()
    {
        // Очистка таблиц перед добавлением новых данных
        $this->clearTables();

        // Проверка на существующие данные
        if ($this->dataExists()) {
            $this->warn('Данные уже существуют в базе!');
            return;
        }

        try {
            DB::transaction(function () {
                // Создаем склады
                $warehouses = $this->createWarehouses();

                // Создаем товары
                $products = $this->createProducts();

                // Распределяем остатки по складам
                $this->populateStocks($warehouses, $products);
            });

            $this->info('Тестовые данные успешно загружены!');
        } catch (\Throwable $e) {
            $this->error('Ошибка при заполнении данных: ' . $e->getMessage());
            $this->output->error($e->getTraceAsString());
        }
    }

    /**
     * Очистка таблиц перед добавлением новых данных.
     *
     * @return void
     */
    protected function clearTables()
    {
        // Отключаем проверку внешних ключей для очистки таблиц
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        // Очистка таблиц
        Stock::truncate();
        Product::truncate();
        Warehouse::truncate();

        // Включаем проверку внешних ключей обратно
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    /**
     * Проверка на существующие данные.
     *
     * @return bool
     */
    protected function dataExists(): bool
    {
        return Stock::exists() || Product::exists() || Warehouse::exists();
    }

    /**
     * Создание тестовых складов.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    protected function createWarehouses()
    {
        $names = ['Центральный склад', 'Склад №2', 'Региональный склад'];

        // Создаем склады с использованием метода create()
        return Warehouse::insert(array_map(fn($name) => ['name' => $name], $names)) ? Warehouse::all() : collect();
    }

    /**
     * Создание тестовых товаров.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    protected function createProducts()
    {
        $products = [
            ['name' => 'Ноутбук', 'price' => 50000],
            ['name' => 'Смартфон', 'price' => 25000],
            ['name' => 'Планшет', 'price' => 30000],
            ['name' => 'Наушники', 'price' => 5000],
            ['name' => 'Внешний аккумулятор', 'price' => 2000],
        ];

        // Создаем продукты с использованием метода create()
        return Product::insert(array_map(fn($product) => [
            'name' => $product['name'],
            'price' => $product['price']
        ], $products)) ? Product::all() : collect();
    }

    /**
     * Распределение остатков по складам.
     *
     * @param \Illuminate\Database\Eloquent\Collection $warehouses
     * @param \Illuminate\Database\Eloquent\Collection $products
     * @return void
     */
    protected function populateStocks($warehouses, $products): void
    {
        $stockData = [];

        // Подготовим данные для вставки в таблицу Stock
        foreach ($warehouses as $warehouse) {
            foreach ($products as $product) {
                $stockData[] = [
                    'warehouse_id' => $warehouse->id,
                    'product_id' => $product->id,
                    'stock' => rand(10, 100)
                ];
            }
        }

        // Вставляем данные сразу в таблицу stock
        Stock::insert($stockData);
    }
}

