<?php

namespace App\Http\Controllers\Api;

use App\Models\Review;
use App\Models\Product;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\Controller;

class ReviewController extends Controller
{
    /**
     * Оставить отзыв на товар
     * POST /api/products/{id}/review
     */
    public function store($productId, Request $request)
    {
        $validator = Validator::make($request->all(), [
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'required|string|min:3|max:1000',
            'images' => 'nullable|array',
            'images.*' => 'string|url'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $product = Product::find($productId);
        if (!$product) {
            return response()->json([
                'message' => 'Product not found'
            ], 404);
        }

        // Проверяем, покупал ли пользователь этот товар
        $hasPurchased = Order::where('user_id', $request->user()->id)
            ->where('status', 'delivered')
            ->whereHas('items', function($query) use ($productId) {
                $query->where('product_id', $productId);
            })
            ->exists();

        // Проверяем, не оставлял ли уже отзыв
        $existingReview = Review::where('product_id', $productId)
            ->where('user_id', $request->user()->id)
            ->first();

        if ($existingReview) {
            return response()->json([
                'message' => 'You have already reviewed this product'
            ], 422);
        }

        $review = Review::create([
            'product_id' => $productId,
            'user_id' => $request->user()->id,
            'rating' => $request->rating,
            'comment' => $request->comment,
            'images' => $request->images,
            'is_verified' => $hasPurchased
        ]);

        return response()->json([
            'message' => 'Review submitted successfully',
            'data' => $review->load('user')
        ], 201);
    }

    /**
     * Получить отзывы на товар
     * GET /api/products/{id}/reviews
     */
    public function index($productId, Request $request)
    {
        $product = Product::find($productId);
        if (!$product) {
            return response()->json([
                'message' => 'Product not found'
            ], 404);
        }

        $reviews = Review::with('user')
            ->where('product_id', $productId)
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 10));

        $averageRating = $product->reviews()->avg('rating');

        return response()->json([
            'data' => $reviews->items(),
            'meta' => [
                'average_rating' => round($averageRating, 1),
                'total_reviews' => $product->reviews()->count(),
                'current_page' => $reviews->currentPage(),
                'last_page' => $reviews->lastPage(),
                'per_page' => $reviews->perPage(),
                'total' => $reviews->total(),
            ]
        ]);
    }

    /**
     * Обновить свой отзыв
     * PUT /api/reviews/{id}
     */
    public function update($id, Request $request)
    {
        $review = Review::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$review) {
            return response()->json([
                'message' => 'Review not found or you do not have permission to edit it'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'rating' => 'sometimes|integer|min:1|max:5',
            'comment' => 'sometimes|string|min:3|max:1000',
            'images' => 'nullable|array'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $review->update($request->only(['rating', 'comment', 'images']));

        return response()->json([
            'message' => 'Review updated successfully',
            'data' => $review->load('user')
        ]);
    }

    /**
     * Удалить свой отзыв
     * DELETE /api/reviews/{id}
     */
    public function destroy($id, Request $request)
    {
        $review = Review::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$review) {
            return response()->json([
                'message' => 'Review not found or you do not have permission to delete it'
            ], 404);
        }

        $review->delete();

        return response()->json([
            'message' => 'Review deleted successfully'
        ]);
    }
}