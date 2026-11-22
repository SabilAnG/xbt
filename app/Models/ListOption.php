<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Pilihan dropdown dari sheet "Daftar" (model motor, finishing, tipe, ukuran,
 * merek, saluran jual). Menambah pilihan di sini langsung muncul di form.
 */
class ListOption extends Model
{
    public const LISTS = [
        'model_motor' => 'Model Motor',
        'finishing' => 'Finishing',
        'tipe' => 'Tipe',
        'ukuran' => 'Ukuran',
        'saluran' => 'Saluran Jual',
    ];

    protected $fillable = ['list', 'value', 'sort_order'];

    /**
     * @return Collection<int, string>
     */
    public static function valuesFor(string $list): Collection
    {
        return static::query()->where('list', $list)->orderBy('sort_order')->orderBy('id')->pluck('value');
    }
}
