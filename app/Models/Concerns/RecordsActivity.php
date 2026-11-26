<?php

namespace App\Models\Concerns;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Catat siapa membuat/menghapus data ini. Perubahan hanya dicatat bila kolom
 * di activityFields() berubah, supaya hitung ulang otomatis tidak jadi riwayat.
 */
trait RecordsActivity
{
    public static function bootRecordsActivity(): void
    {
        static::created(fn (Model $model) => $model->logActivity('created'));
        static::deleted(fn (Model $model) => $model->logActivity('deleted'));
        static::updated(function (Model $model) {
            if (array_intersect(array_keys($model->getChanges()), $model->activityFields()) !== []) {
                $model->logActivity('updated');
            }
        });
    }

    /** Label singkat data, mis. "Penjualan PJ-2612-0001". */
    abstract public function activityLabel(): string;

    /** @return list<string> */
    public function activityFields(): array
    {
        return [];
    }

    public function logActivity(string $action): void
    {
        if (! Auth::check()) {
            return;
        }

        ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => $action,
            'subject_type' => static::class,
            'subject_id' => $this->getKey(),
            'description' => mb_substr($this->activityLabel(), 0, 255),
        ]);
    }

    /** Nama pengguna yang mencatat data ini. */
    public function recordedBy(): ?string
    {
        return ActivityLog::query()->with('user')->where('subject_type', static::class)->where('subject_id', $this->getKey())
            ->where('action', 'created')->first()?->user?->name;
    }
}
