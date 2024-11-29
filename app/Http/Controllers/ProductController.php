<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        // Получение списка товаров с остатками по складам
        $products = Product::with(['stocks' => function($query) {
            $query->with('warehouse');
        }])->get();

        return response()->json($products);
    }
}
