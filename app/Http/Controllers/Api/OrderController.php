<?php

namespace App\Http\Controllers\Api;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Cart;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use App\Http\Controllers\Controller;

class OrderController extends Controller
{
    /**
     * Список заказов пользователя
     * GET /api/orders
     */
    public function index(Request $request)
    {
        $orders = Order::with(['items.product'])
            ->where('user_id', $request->user()->id)
            ->orderBy('created_at', 'desc')
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
     * Просмотр конкретного заказа
     * GET /api/orders/{id}
     */
    public function show($id, Request $request)
    {
        $order = Order::with(['items.product.images'])
            ->where('user_id', $request->user()->id)
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
     * Создать заказ из корзины
     * POST /api/orders
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'shipping_address' => 'required|string|max:500',
            'shipping_city' => 'required|string|max:100',
            'shipping_country' => 'required|string|max:100',
            'shipping_phone' => 'nullable|string|max:20',
            'notes' => 'nullable|string',
            'payment_method' => 'nullable|string|in:card,cash,bank_transfer'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        // Получаем корзину пользователя
        $cartItems = Cart::with('product')
            ->where('user_id', $request->user()->id)
            ->get();

        if ($cartItems->isEmpty()) {
            return response()->json([
                'message' => 'Cart is empty'
            ], 422);
        }

        // Проверяем наличие товаров
        foreach ($cartItems as $item) {
            if ($item->product->stock < $item->quantity) {
                return response()->json([
                    'message' => "Not enough stock for product: {$item->product->name}",
                    'product' => $item->product->name,
                    'available' => $item->product->stock
                ], 422);
            }
        }

        // Рассчитываем сумму
        $subtotal = $cartItems->sum(function($item) {
            return $item->quantity * $item->product->price;
        });

        $tax = $subtotal * 0.13; // 13% НДС
        $shippingCost = $subtotal > 5000 ? 0 : 500; // Бесплатная доставка от 5000
        $total = $subtotal + $tax + $shippingCost;

        // Создаем заказ
        $order = Order::create([
            'order_number' => 'ORD-' . Str::random(10) . '-' . time(),
            'user_id' => $request->user()->id,
            'total_price' => $total,
            'subtotal' => $subtotal,
            'tax' => $tax,
            'shipping_cost' => $shippingCost,
            'discount' => 0,
            'status' => 'pending',
            'payment_status' => 'pending',
            'payment_method' => $request->payment_method ?? 'card',
            'shipping_address' => $request->shipping_address,
            'shipping_city' => $request->shipping_city,
            'shipping_country' => $request->shipping_country,
            'shipping_phone' => $request->shipping_phone,
            'notes' => $request->notes,
        ]);

        // Создаем позиции заказа и уменьшаем сток
        foreach ($cartItems as $item) {
            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $item->product_id,
                'quantity' => $item->quantity,
                'price' => $item->product->price,
                'options' => $item->options
            ]);

            // Уменьшаем сток
            $product = $item->product;
            $product->decrement('stock', $item->quantity);
        }

        // Очищаем корзину
        Cart::where('user_id', $request->user()->id)->delete();

        return response()->json([
            'message' => 'Order created successfully',
            'data' => $order->load('items.product')
        ], 201);
    }

    /**
     * Отменить заказ
     * PUT /api/orders/{id}/cancel
     */
    public function cancel($id, Request $request)
    {
        $order = Order::where('user_id', $request->user()->id)
            ->whereIn('status', ['pending', 'processing'])
            ->find($id);

        if (!$order) {
            return response()->json([
                'message' => 'Order not found or cannot be cancelled'
            ], 404);
        }

        // Возвращаем товары на склад
        foreach ($order->items as $item) {
            $product = Product::find($item->product_id);
            if ($product) {
                $product->increment('stock', $item->quantity);
            }
        }

        $order->update(['status' => 'cancelled']);

        return response()->json([
            'message' => 'Order cancelled successfully',
            'data' => $order
        ]);
    }
}