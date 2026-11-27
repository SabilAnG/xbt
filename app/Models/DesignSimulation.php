<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use App\Support\ExhaustSimulator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Desain knalpot hasil Simulasi Desain (parametrik + foto referensi). */
class DesignSimulation extends Model
{
    use RecordsActivity;

    protected $fillable = ['name', 'motor', 'params', 'references', 'motor_photos', 'thumbnail', 'notes', 'user_id'];

    protected function casts(): array
    {
        return [
            'params' => 'array',
            'references' => 'array',
            'motor_photos' => 'array',
        ];
    }

    public function activityLabel(): string
    {
        return 'Simulasi desain '.$this->name;
    }

    /** @return list<string> */
    public function activityFields(): array
    {
        return ['name', 'motor'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return array<string, mixed> */
    public function result(): array
    {
        return ExhaustSimulator::simulate($this->params ?? []);
    }

    public function motorLabel(): string
    {
        return ExhaustSimulator::MOTORS[$this->motor]['label'] ?? 'Motor lain';
    }

    /** @return list<string> */
    public function motorPhotoUrls(): array
    {
        return collect($this->motor_photos ?? [])->map(fn (string $path) => asset($path))->all();
    }

    /** @return list<string> */
    public function referenceUrls(): array
    {
        return collect($this->references ?? [])->map(fn (string $path) => asset($path))->all();
    }
}
