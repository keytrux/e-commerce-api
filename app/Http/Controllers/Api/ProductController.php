<?php

namespace App\Http\Controllers\Api;

use App\Models\Product;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class ProductController extends Controller
{
    /**
     * Список всех товаров с фильтрацией
     */
    public function index(Request $request)
    {
        $query = Product::query()
            ->with(['category', 'brand', 'images'])
            ->where('status', 'active');

        // Фильтр по категории
        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        // Фильтр по бренду
        if ($request->has('brand_id')) {
            $query->where('brand_id', $request->brand_id);
        }

        // Фильтр по цене
        if ($request->has('min_price')) {
            $query->where('price', '>=', $request->min_price);
        }
        if ($request->has('max_price')) {
            $query->where('price', '<=', $request->max_price);
        }

        // Поиск по названию
        if ($request->has('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        // Сортировка
        $sortField = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortField, $sortOrder);

        $products = $query->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => $products->items(),
            'meta' => [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
            ],
            'links' => [
                'first' => $products->url(1),
                'last' => $products->url($products->lastPage()),
                'prev' => $products->previousPageUrl(),
                'next' => $products->nextPageUrl(),
            ]
        ]);
    }

    /**
     * Просмотр конкретного товара
     */
    public function show($id)
    {
        $product = Product::with(['category', 'brand', 'images', 'attributes', 'reviews'])
            ->where('status', 'active')
            ->find($id);

        if (!$product) {
            return response()->json([
                'message' => 'Product not found'
            ], 404);
        }

        // Увеличиваем счетчик просмотров
        $product->increment('views_count');

        // Добавляем средний рейтинг
        $averageRating = $product->reviews()->avg('rating');
        $product->average_rating = round($averageRating, 1);

        return response()->json([
            'data' => $product
        ]);
    }

    /**
     * Получить похожие товары
     */
    public function similar($id)
    {
        // Находим текущий товар
        $product = Product::where('status', 'active')->find($id);
        
        if (!$product) {
            return response()->json([
                'message' => 'Product not found'
            ], 404);
        }

        // Ищем похожие товары (по категории, бренду, ценовому диапазону)
        $similarProducts = Product::with(['images' => function($query) {
                $query->where('is_main', true);
            }, 'brand'])
            ->where('status', 'active')
            ->where('id', '!=', $id)
            ->where(function($query) use ($product) {
                // Похожие по категории
                $query->where('category_id', $product->category_id)
                      // Или по бренду
                      ->orWhere('brand_id', $product->brand_id);
            })
            // Похожий ценовой диапазон (±30%)
            ->whereBetween('price', [
                $product->price * 0.7,  // 70% от цены
                $product->price * 1.3   // 130% от цены
            ])
            ->orderByRaw("CASE 
                WHEN category_id = ? THEN 1 
                WHEN brand_id = ? THEN 2 
                ELSE 3 
            END", [$product->category_id, $product->brand_id])
            ->limit(8)
            ->get();

        // Если мало похожих товаров, добавляем популярные товары
        if ($similarProducts->count() < 4) {
            $additionalProducts = Product::with(['images' => function($query) {
                    $query->where('is_main', true);
                }, 'brand'])
                ->where('status', 'active')
                ->where('id', '!=', $id)
                ->whereNotIn('id', $similarProducts->pluck('id')->toArray())
                ->orderBy('views_count', 'desc')
                ->limit(8 - $similarProducts->count())
                ->get();
            
            $similarProducts = $similarProducts->merge($additionalProducts);
        }

        return response()->json([
            'data' => $similarProducts,
            'meta' => [
                'total' => $similarProducts->count(),
                'product_id' => $id,
                'product_name' => $product->name,
            ]
        ]);
    }
}