<?php

namespace App\Http\Controllers;

use App\Models\Warehouse;
use Illuminate\Http\Request;

class WarehouseController extends Controller
{
    // Метод для получения списка всех складов
    public function index()
    {
        $warehouses = Warehouse::all();  // Получаем все склады
        return response()->json($warehouses);
    }

    // Метод для создания нового склада
    public function store(Request $request)
    {
        // Валидация данных запроса
        $validated = $request->validate([
            'name' => 'required|string|max:255',  // Проверка имени склада
        ]);

        // Создание нового склада
        $warehouse = Warehouse::create([
            'name' => $validated['name'],
        ]);

        return response()->json($warehouse, 201);  // Возвращаем созданный склад с кодом 201
    }

    // Метод для получения информации о складе по ID
    public function show($id)
    {
        $warehouse = Warehouse::find($id);

        if (!$warehouse) {
            return response()->json(['message' => 'Warehouse not found'], 404);
        }

        return response()->json($warehouse);
    }

    // Метод для обновления информации о складе
    public function update(Request $request, $id)
    {
        $warehouse = Warehouse::find($id);

        if (!$warehouse) {
            return response()->json(['message' => 'Warehouse not found'], 404);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $warehouse->update([
            'name' => $validated['name'],
        ]);

        return response()->json($warehouse);
    }

    // Метод для удаления склада
    public function destroy($id)
    {
        $warehouse = Warehouse::find($id);

        if (!$warehouse) {
            return response()->json(['message' => 'Warehouse not found'], 404);
        }

        $warehouse->delete();
        return response()->json(['message' => 'Warehouse deleted']);
    }
}
