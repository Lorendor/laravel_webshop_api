<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'total' => (float) $this->total,
            'formatted_total' => '$' . number_format($this->total, 2),
            'customer_email' => $this->customer_email,
            'order_items' => OrderItemResource::collection($this->whenLoaded('orderItems')),
            'download_url' => $this->when(
                $this->isCompleted(),
                route('api.orders.download', ['order' => $this->id, 'token' => $this->download_token])
            ),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
