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
}