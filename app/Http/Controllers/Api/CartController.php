<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class CartController extends Controller
{
    /**
     * Get the cart key for the current user.
     */
    private function getCartKey(?int $userId): string
    {
        return $userId ? "cart:user:{$userId}" : "cart:guest:" . request()->ip();
    }

    /**
     * Get the user's cart.
     */
    public function index(Request $request): JsonResponse
    {
        $cartKey = $this->getCartKey($request->user()?->id);
        $cart = Cache::get($cartKey, []);

        $items = [];
        $total = 0;

        foreach ($cart as $productId => $quantity) {
            $product = Product::active()->find($productId);
            if ($product) {
                $itemTotal = $product->price * $quantity;
                $total += $itemTotal;

                $items[] = [
                    'product' => new ProductResource($product),
                    'quantity' => $quantity,
                    'unit_price' => (float) $product->price,
                    'total' => (float) $itemTotal,
                ];
            }
        }

        return response()->json([
            'items' => $items,
            'total' => (float) $total,
            'formatted_total' => '$' . number_format($total, 2),
            'item_count' => count($items),
        ]);
    }

    /**
     * Add item to cart.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1|max:10',
        ]);

        $product = Product::active()->findOrFail($validated['product_id']);

        $cartKey = $this->getCartKey($request->user()?->id);
        $cart = Cache::get($cartKey, []);

        $productId = $validated['product_id'];
        $quantity = $validated['quantity'];

        if (isset($cart[$productId])) {
            $cart[$productId] += $quantity;
        } else {
            $cart[$productId] = $quantity;
        }

        // Limit max quantity
        $cart[$productId] = min($cart[$productId], 10);

        Cache::put($cartKey, $cart, now()->addDays(7));

        return response()->json([
            'message' => 'Item added to cart',
            'cart' => $cart,
        ], 201);
    }

    /**
     * Update item quantity in cart.
     */
    public function update(Request $request, int $productId): JsonResponse
    {
        $validated = $request->validate([
            'quantity' => 'required|integer|min:0|max:10',
        ]);

        $cartKey = $this->getCartKey($request->user()?->id);
        $cart = Cache::get($cartKey, []);

        if ($validated['quantity'] === 0) {
            unset($cart[$productId]);
        } else {
            $cart[$productId] = $validated['quantity'];
        }

        Cache::put($cartKey, $cart, now()->addDays(7));

        return response()->json([
            'message' => 'Cart updated',
            'cart' => $cart,
        ]);
    }

    /**
     * Remove item from cart.
     */
    public function destroy(Request $request, int $productId): JsonResponse
    {
        $cartKey = $this->getCartKey($request->user()?->id);
        $cart = Cache::get($cartKey, []);

        unset($cart[$productId]);

        Cache::put($cartKey, $cart, now()->addDays(7));

        return response()->json([
            'message' => 'Item removed from cart',
            'cart' => $cart,
        ]);
    }

    /**
     * Clear the entire cart.
     */
    public function clear(Request $request): JsonResponse
    {
        $cartKey = $this->getCartKey($request->user()?->id);
        Cache::forget($cartKey);

        return response()->json([
            'message' => 'Cart cleared',
        ]);
    }
}
