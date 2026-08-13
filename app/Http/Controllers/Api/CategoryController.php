<?php

namespace App\Http\Controllers\Api;

use App\Models\Category;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class CategoryController extends Controller
{
    /**
     * Список всех категорий
     */
    public function index(Request $request)
    {
        $categories = Category::query()
            ->where('is_active', true)
            ->when($request->has('parent_id'), function ($query) use ($request) {
                return $query->where('parent_id', $request->parent_id);
            })
            ->when($request->has('with_products'), function ($query) {
                return $query->withCount('products');
            })
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => $categories
        ]);
    }

    /**
     * Получить дерево категорий
     */
    public function tree()
    {
        $categories = Category::with(['children' => function ($query) {
            $query->with(['children'])->where('is_active', true);
        }])
        ->whereNull('parent_id')
        ->where('is_active', true)
        ->get();

        return response()->json([
            'data' => $categories
        ]);
    }

    /**
     * Получить товары по категории
     */
    public function products($id, Request $request)
    {
        // Находим категорию
        $category = Category::where('is_active', true)->find($id);
        
        if (!$category) {
            return response()->json([
                'message' => 'Category not found'
            ], 404);
        }

        // Получаем товары категории
        $query = $category->products()
            ->with(['images' => function($query) {
                $query->where('is_main', true);
            }, 'brand'])
            ->where('status', 'active');

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
        $allowedSortFields = ['name', 'price', 'created_at'];
        $sortField = $request->get('sort_by', 'created_at');
        $sortField = in_array($sortField, $allowedSortFields) ? $sortField : 'created_at';
        
        $sortOrder = $request->get('sort_order', 'desc');
        $sortOrder = in_array($sortOrder, ['asc', 'desc']) ? $sortOrder : 'desc';
        
        $query->orderBy($sortField, $sortOrder);

        $perPage = $request->get('per_page', 15);
        $products = $query->paginate($perPage);

        return response()->json([
            'category' => [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
            ],
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
}