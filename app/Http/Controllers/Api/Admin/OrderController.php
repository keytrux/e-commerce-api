<?php

namespace App\Http\Controllers\Api\Admin;

use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\Controller;
use Carbon\Carbon;

class OrderController extends Controller
{
    /**
     * Список всех заказов (админ)
     * GET /api/admin/orders
     */
    public function index(Request $request)
    {
        $query = Order::with(['user', 'items.product']);

        // Фильтры
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        if ($request->has('payment_status')) {
            $query->where('payment_status', $request->payment_status);
        }
        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $orders = $query->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => $orders->items(),
            'meta' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
            ]
        ]);
    }

    /**
     * Обновить статус заказа
     * PUT /api/admin/orders/{id}/status
     */
    public function updateStatus(Request $request, $id)
    {
        $order = Order::find($id);
        if (!$order) {
            return response()->json([
                'message' => 'Order not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'required|in:pending,processing,shipped,delivered,cancelled',
            'tracking_number' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $data = ['status' => $request->status];
        
        if ($request->status === 'shipped') {
            $data['shipped_at'] = now();
        }
        if ($request->status === 'delivered') {
            $data['delivered_at'] = now();
        }
        if ($request->status === 'cancelled') {
            // Возвращаем товары на склад
            foreach ($order->items as $item) {
                $product = Product::find($item->product_id);
                if ($product) {
                    $product->increment('stock', $item->quantity);
                }
            }
        }

        $order->update($data);

        return response()->json([
            'message' => 'Order status updated successfully',
            'data' => $order->fresh(['user', 'items'])
        ]);
    }

    /**
     * Просмотр конкретного заказа (админ)
     * GET /api/admin/orders/{id}
     */
    public function show($id)
    {
        $order = Order::with(['user', 'items.product.images'])
            ->find($id);

        if (!$order) {
            return response()->json([
                'message' => 'Order not found'
            ], 404);
        }

        return response()->json([
            'data' => $order
        ]);
    }

    /**
     * Отчет по продажам
     * GET /api/admin/reports/sales
     */
    public function salesReport(Request $request)
    {
        $period = $request->get('period', 'month');
        
        $dateFrom = match($period) {
            'week' => Carbon::now()->subWeek(),
            'month' => Carbon::now()->subMonth(),
            'year' => Carbon::now()->subYear(),
            default => Carbon::now()->subMonth(),
        };

        // Общая статистика
        $totalOrders = Order::where('created_at', '>=', $dateFrom)->count();
        $totalRevenue = Order::where('created_at', '>=', $dateFrom)
            ->where('status', '!=', 'cancelled')
            ->sum('total_price');
        $averageOrderValue = $totalOrders > 0 ? $totalRevenue / $totalOrders : 0;

        // Топ товаров
        $topProducts = Order::where('created_at', '>=', $dateFrom)
            ->with('items.product')
            ->get()
            ->flatMap(function($order) {
                return $order->items;
            })
            ->groupBy('product_id')
            ->map(function($items) {
                return [
                    'product_id' => $items->first()->product_id,
                    'product_name' => $items->first()->product->name ?? 'Unknown',
                    'total_quantity' => $items->sum('quantity'),
                    'total_revenue' => $items->sum(function($item) {
                        return $item->quantity * $item->price;
                    })
                ];
            })
            ->sortByDesc('total_revenue')
            ->take(10)
            ->values();

        // Статистика по статусам
        $statusStats = Order::where('created_at', '>=', $dateFrom)
            ->groupBy('status')
            ->selectRaw('status, count(*) as count')
            ->pluck('count', 'status');

        // Ежедневные продажи
        $dailySales = Order::where('created_at', '>=', $dateFrom)
            ->where('status', '!=', 'cancelled')
            ->groupBy('date')
            ->orderBy('date', 'asc')
            ->selectRaw('DATE(created_at) as date, SUM(total_price) as total')
            ->pluck('total', 'date');

        return response()->json([
            'data' => [
                'period' => $period,
                'summary' => [
                    'total_orders' => $totalOrders,
                    'total_revenue' => $totalRevenue,
                    'average_order_value' => round($averageOrderValue, 2),
                ],
                'top_products' => $topProducts,
                'status_distribution' => $statusStats,
                'daily_sales' => $dailySales,
            ]
        ]);
    }
}