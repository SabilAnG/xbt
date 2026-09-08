<?php

namespace App\Models;

use Database\Factories\ShipmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shipment extends Model
{
    /** @use HasFactory<ShipmentFactory> */
    use HasFactory;

    protected $fillable = [
        'order_id',
        'provider',
        'tracking_number',
        'status',
        'status_description',
        'status_updated_at',
        'last_checked',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'status_updated_at' => 'datetime',
            'last_checked' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Newest first — the tracking timeline marks index 0 as the active step.
     */
    public function events(): HasMany
    {
        return $this->hasMany(ShipmentEvent::class)->orderByDesc('happened_at');
    }

    public function toTrackingPayload(): array
    {
        return [
            'provider' => $this->provider,
            'tracking_number' => $this->tracking_number,
            'status' => $this->status,
            'status_description' => $this->status_description,
            'status_updated_at' => $this->status_updated_at?->toIso8601String(),
            'last_checked' => $this->last_checked?->toIso8601String(),
            'events' => $this->events->map->toTrackingPayload()->all(),
        ];
    }
}
