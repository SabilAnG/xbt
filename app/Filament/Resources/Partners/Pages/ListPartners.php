<?php

namespace App\Filament\Resources\Partners\Pages;

use App\Filament\Resources\Partners\PartnerResource;
use Filament\Resources\Pages\ListRecords;

class ListPartners extends ListRecords
{
    protected static string $resource = PartnerResource::class;

    /**
     * Tidak ada tombol tambah: partner datang lewat formulir publik, bukan
     * didaftarkan admin. Membuatnya dari sini akan melewati sandi yang cuma
     * diketahui pemiliknya.
     */
    protected function getHeaderActions(): array
    {
        return [];
    }
}
