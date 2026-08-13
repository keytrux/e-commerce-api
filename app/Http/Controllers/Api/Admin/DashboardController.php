<?php

namespace App\Http\Controllers\Api\Admin;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class DashboardController extends Controller
{
    /**
     * Получить статистику для дашборда
     * GET /api/admin/dashboard
     */
    public function index(Request $request)
    {
        // Общая статистика
        $totalProducts = Product::count();
        $activeProducts = Product::where('status', 'active')->count();
        $totalUsers = User::count();
        $totalOrders = Order::count();
        $pendingOrders = Order::where('status', 'pending')->count();

        // Доходы
        $todayRevenue = Order::whereDate('created_at', today())
            ->where('status', '!=', 'cancelled')
            ->sum('total_price');
            
        $monthRevenue = Order::whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->where('status', '!=', 'cancelled')
            ->sum('total_price');

        // Топ категорий
        $topCategories = Product::selectRaw('category_id, COUNT(*) as count')
            ->with('category')
            ->groupBy('category_id')
            ->orderBy('count', 'desc')
            ->limit(5)
            ->get()
            ->map(function($item) {
                return [
                    'category_name' => $item->category->name ?? 'Unknown',
                    'products_count' => $item->count
                ];
            });

        // Недавние заказы
        $recentOrders = Order::with(['user'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        // Низкий запас товаров
        $lowStockProducts = Product::where('stock', '<', 10)
            ->where('status', 'active')
            ->orderBy('stock', 'asc')
            ->limit(10)
            ->get(['id', 'name', 'stock', 'sku']);

        return response()->json([
            'data' => [
                'stats' => [
                    'total_products' => $totalProducts,
                    'active_products' => $activeProducts,
                    'total_users' => $totalUsers,
                    'total_orders' => $totalOrders,
                    'pending_orders' => $pendingOrders,
                ],
                'revenue' => [
                    'today' => $todayRevenue,
                    'this_month' => $monthRevenue,
                ],
                'top_categories' => $topCategories,
                'recent_orders' => $recentOrders,
                'low_stock_products' => $lowStockProducts,
            ]
        ]);
    }
}