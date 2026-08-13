<?php

namespace App\Http\Controllers\Api;

use App\Models\Cart;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\Controller;

class CartController extends Controller
{
    /**
     * Получить корзину пользователя
     * GET /api/cart
     */
    public function index(Request $request)
    {
        $cart = Cart::with(['product' => function($query) {
            $query->with(['images' => function($q) {
                $q->where('is_main', true);
            }]);
        }])
        ->where('user_id', $request->user()->id)
        ->get();

        $total = $cart->sum(function($item) {
            return $item->quantity * $item->product->price;
        });

        return response()->json([
            'data' => $cart,
            'meta' => [
                'total_items' => $cart->sum('quantity'),
                'total_price' => $total,
                'currency' => 'RUB'
            ]
        ]);
    }

    /**
     * Добавить товар в корзину
     * POST /api/cart/add
     */
    public function add(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1',
            'options' => 'nullable|array'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $product = Product::find($request->product_id);
        
        // Проверяем наличие товара
        if ($product->stock < $request->quantity) {
            return response()->json([
                'message' => 'Not enough stock available'
            ], 422);
        }

        // Ищем товар в корзине
        $cartItem = Cart::where('user_id', $request->user()->id)
            ->where('product_id', $request->product_id)
            ->first();

        if ($cartItem) {
            // Обновляем количество
            $newQuantity = $cartItem->quantity + $request->quantity;
            
            if ($product->stock < $newQuantity) {
                return response()->json([
                    'message' => 'Not enough stock available'
                ], 422);
            }
            
            $cartItem->update([
                'quantity' => $newQuantity,
                'options' => $request->options ?? $cartItem->options
            ]);
        } else {
            // Создаем новый элемент
            $cartItem = Cart::create([
                'user_id' => $request->user()->id,
                'product_id' => $request->product_id,
                'quantity' => $request->quantity,
                'options' => $request->options
            ]);
        }

        return response()->json([
            'message' => 'Product added to cart',
            'data' => $cartItem->load('product')
        ], 201);
    }

    /**
     * Обновить количество товара в корзине
     * PUT /api/cart/update
     */
    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $cartItem = Cart::where('user_id', $request->user()->id)
            ->where('product_id', $request->product_id)
            ->first();

        if (!$cartItem) {
            return response()->json([
                'message' => 'Item not found in cart'
            ], 404);
        }

        $product = Product::find($request->product_id);
        
        if ($product->stock < $request->quantity) {
            return response()->json([
                'message' => 'Not enough stock available'
            ], 422);
        }

        $cartItem->update(['quantity' => $request->quantity]);

        return response()->json([
            'message' => 'Cart updated successfully',
            'data' => $cartItem->load('product')
        ]);
    }

    /**
     * Удалить товар из корзины
     * DELETE /api/cart/remove/{id}
     */
    public function remove($id, Request $request)
    {
        $cartItem = Cart::where('user_id', $request->user()->id)
            ->where('id', $id)
            ->first();

        if (!$cartItem) {
            return response()->json([
                'message' => 'Item not found in cart'
            ], 404);
        }

        $cartItem->delete();

        return response()->json([
            'message' => 'Item removed from cart'
        ]);
    }

    /**
     * Очистить корзину
     * DELETE /api/cart/clear
     */
    public function clear(Request $request)
    {
        Cart::where('user_id', $request->user()->id)->delete();

        return response()->json([
            'message' => 'Cart cleared successfully'
        ]);
    }
}