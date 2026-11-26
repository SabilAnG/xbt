<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Jejak siapa mencatat, mengubah, atau menghapus transaksi. */
class ActivityLog extends Model
{
    public const UPDATED_AT = null;

    public const ACTIONS = ['created' => 'Mencatat', 'updated' => 'Mengubah', 'deleted' => 'Menghapus'];

    protected $fillable = ['user_id', 'action', 'subject_type', 'subject_id', 'description'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function actionLabel(): string
    {
        return self::ACTIONS[$this->action] ?? $this->action;
    }
}
