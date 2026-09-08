<?php

namespace App\Models;

use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    public const STATUSES = [
        'processing' => 'Processing',
        'shipped' => 'Shipped',
        'in_transit' => 'In transit',
        'delivered' => 'Delivered',
        'cancelled' => 'Cancelled',
    ];

    protected $fillable = [
        'order_number',
        'customer_name',
        'customer_email',
        'customer_phone',
        'status',
        'notes',
    ];

    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class)->orderBy('sort_order');
    }

    /**
     * Shape the tracking page's JavaScript expects under `data.shipments`.
     */
    public function toTrackingPayload(): array
    {
        return [
            'order_ref_number' => $this->order_number,
            'status' => $this->status,
            'shipments' => $this->shipments->map->toTrackingPayload()->all(),
        ];
    }
}
