<?php

namespace App\Filament\Resources;

use App\Support\HakPartner;
use Filament\Resources\Resource;

/**
 * Induk seluruh resource panel.
 *
 * Ada demi satu hal: menyaring menu di panel partner sesuai hak yang dicentang
 * admin. Di panel pusat tidak mengubah apa pun.
 *
 * Diletakkan di `canAccess()` karena Filament memakainya untuk dua hal
 * sekaligus — menyembunyikan menu dari navigasi, dan menolak halamannya dengan
 * 403. Menyembunyikan saja bukan penjagaan: alamatnya masih bisa diketik.
 */
abstract class BaseResource extends Resource
{
    public static function canAccess(): bool
    {
        return HakPartner::boleh(static::class, static::getNavigationGroup())
            && parent::canAccess();
    }
}
