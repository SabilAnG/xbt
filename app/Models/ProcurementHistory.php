<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Arsip "Riwayat Pengadaan" — acuan harga, tidak dihitung ulang di ringkasan. */
class ProcurementHistory extends Model
{
    protected $fillable = [
        'number', 'date', 'period', 'name', 'kind', 'category', 'form', 'diameter',
        'thickness', 'grade', 'qty', 'unit', 'unit_price', 'total', 'description',
    ];

    protected function casts(): array
    {
        return ['date' => 'date', 'qty' => 'decimal:3', 'unit_price' => 'decimal:2', 'total' => 'decimal:2'];
    }
}
