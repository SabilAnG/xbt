<?php

namespace App\Http\Controllers\Admin\Concerns;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Unggahan gambar website disimpan di disk "site" (berakar di public/), jadi
 * path yang tersimpan langsung bisa dipakai asset() — sama dengan gambar lama
 * yang dibawa dari situs sebelumnya.
 */
trait StoresSiteUploads
{
    protected function storeSiteUpload(UploadedFile $file, string $directory): string
    {
        return $file->store('uploads/'.$directory, 'site');
    }

    /** Hapus berkas lama hanya bila memang hasil unggahan admin. */
    protected function deleteSiteUpload(?string $path): void
    {
        if ($path && str_starts_with($path, 'uploads/')) {
            Storage::disk('site')->delete($path);
        }
    }
}
