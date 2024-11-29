<?php

namespace App\Http\Controllers;

use App\DTO\ProductMovementDTO;
use App\Services\ProductMovementService;
use Illuminate\Http\Request;

class ProductMovementController extends Controller
{
    protected $productMovementService;

    public function __construct(ProductMovementService $productMovementService)
    {
        $this->productMovementService = $productMovementService;
    }

    public function addMovement(Request $request)
    {
        $data = new ProductMovementDTO(
            $request->input('product_id'),
            $request->input('warehouse_id'),
            $request->input('quantity'),
            $request->input('type')
        );

        // Передаем DTO в сервис
        $this->productMovementService->handleMovement($data);

        return response()->json(['message' => 'Product movement added successfully'], 201);
    }
}
