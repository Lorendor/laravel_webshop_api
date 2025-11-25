<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class OrderController extends Controller
{
    /**
     * Get the cart key for the current user.
     */
    private function getCartKey(?int $userId): string
    {
        return $userId ? "cart:user:{$userId}" : "cart:guest:" . request()->ip();
    }

    /**
     * Display a listing of user's orders.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();

        $orders = Order::where('user_id', $user->id)
            ->with('orderItems.product')
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return OrderResource::collection($orders);
    }

    /**
     * Create a new order (checkout).
     */
    public function checkout(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'customer_email' => 'required|email',
            'stripe_session_id' => 'nullable|string',
        ]);

        $cartKey = $this->getCartKey($request->user()?->id);
        $cart = Cache::get($cartKey, []);

        if (empty($cart)) {
            return response()->json([
                'message' => 'Cart is empty',
            ], 400);
        }

        // Calculate total and validate products
        $total = 0;
        $orderItems = [];

        foreach ($cart as $productId => $quantity) {
            $product = Product::active()->find($productId);

            if (! $product) {
                return response()->json([
                    'message' => "Product with ID {$productId} not found or inactive",
                ], 400);
            }

            $itemTotal = $product->price * $quantity;
            $total += $itemTotal;

            $orderItems[] = [
                'product_id' => $product->id,
                'quantity' => $quantity,
                'unit_price' => $product->price,
            ];
        }

        // Create order
        $order = Order::create([
            'user_id' => $request->user()?->id,
            'status' => 'completed', // In production, set to 'pending' and update after payment confirmation
            'total' => $total,
            'customer_email' => $validated['customer_email'],
            'stripe_session_id' => $validated['stripe_session_id'] ?? null,
            'download_token' => Str::random(64),
        ]);

        // Create order items
        foreach ($orderItems as $item) {
            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $item['product_id'],
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'],
            ]);
        }

        // Clear cart
        Cache::forget($cartKey);

        $order->load('orderItems.product');

        return response()->json([
            'message' => 'Order created successfully',
            'order' => new OrderResource($order),
        ], 201);
    }

    /**
     * Download products from an order.
     */
    public function download(Request $request, Order $order): JsonResponse|\Illuminate\Http\Response
    {
        $token = $request->query('token');

        // Verify token
        if (! $token || $token !== $order->download_token) {
            return response()->json([
                'message' => 'Invalid download token',
            ], 403);
        }

        // Verify order is completed
        if (! $order->isCompleted()) {
            return response()->json([
                'message' => 'Order is not completed',
            ], 403);
        }

        // Verify user owns the order (if authenticated)
        if ($request->user() && $order->user_id !== $request->user()->id) {
            return response()->json([
                'message' => 'Unauthorized',
            ], 403);
        }

        // Verify email matches (if not authenticated)
        if (! $request->user() && $request->query('email') !== $order->customer_email) {
            return response()->json([
                'message' => 'Email verification required',
            ], 403);
        }

        $order->load('orderItems.product');

        // Create ZIP file with all products
        $zipFileName = 'order_' . $order->id . '_' . time() . '.zip';
        $zipPath = storage_path('app/temp/' . $zipFileName);

        // Ensure temp directory exists
        if (! is_dir(storage_path('app/temp'))) {
            mkdir(storage_path('app/temp'), 0755, true);
        }

        $zip = new \ZipArchive();
        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            return response()->json([
                'message' => 'Failed to create download archive',
            ], 500);
        }

        $filesAdded = 0;
        foreach ($order->orderItems as $orderItem) {
            $product = $orderItem->product;
            $filePath = storage_path('app/' . $product->file_path);

            if (file_exists($filePath)) {
                $zip->addFile($filePath, $product->name . '.' . strtolower($product->file_type));
                $filesAdded++;
            }
        }

        $zip->close();

        // Check if any files were added
        if ($filesAdded === 0) {
            @unlink($zipPath); // Clean up empty zip
            return response()->json([
                'message' => 'No files found for this order',
            ], 404);
        }

        // Return download response
        return response()->download($zipPath, $zipFileName)->deleteFileAfterSend(true);
    }
}
