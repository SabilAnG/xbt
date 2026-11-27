<?php

namespace App\Models;

use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

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
        'sale_id',
        'order_number',
        'tracking_code',
        'customer_name',
        'customer_email',
        'customer_phone',
        'status',
        'notes',
    ];

    protected static function booted(): void
    {
        static::creating(function (Order $order) {
            $order->tracking_code ??= self::newTrackingCode();
        });
    }

    /**
     * Kode yang dibagikan ke pembeli untuk cek pengiriman. Acak (bukan nomor
     * nota berurutan), jadi status pesanan orang lain tidak bisa ditebak.
     */
    public static function newTrackingCode(): string
    {
        do {
            $code = 'HST'.Str::upper(Str::random(13));
        } while (static::where('tracking_code', $code)->exists());

        return $code;
    }

    public function trackingUrl(): string
    {
        return route('tracking', ['kode' => $this->tracking_code]);
    }

    /** Nota penjualan yang dikirim: stok & uangnya dicatat di sana, bukan di pesanan. */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

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
            'order_ref_number' => $this->tracking_code,
            'status' => $this->status,
            'shipments' => $this->shipments->map->toTrackingPayload()->all(),
        ];
    }
}
