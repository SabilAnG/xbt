<?php

namespace App\Models;

use Database\Factories\ShipmentEventFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShipmentEvent extends Model
{
    /** @use HasFactory<ShipmentEventFactory> */
    use HasFactory;

    protected $fillable = [
        'shipment_id',
        'status',
        'description',
        'location',
        'happened_at',
    ];

    protected function casts(): array
    {
        return ['happened_at' => 'datetime'];
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    public function toTrackingPayload(): array
    {
        return [
            'status' => $this->status,
            'description' => $this->description,
            'timestamp' => $this->happened_at?->toIso8601String(),
            // The page reads location.address.addressLocality.
            'location' => $this->location
                ? ['address' => ['addressLocality' => $this->location]]
                : null,
        ];
    }
}
