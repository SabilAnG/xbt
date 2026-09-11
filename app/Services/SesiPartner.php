<?php

namespace App\Services;

use App\Models\Tenant;

/**
 * Mengingat partner mana yang sedang masuk, di sesi.
 *
 * Sesi disimpan di database pusat (`SESSION_CONNECTION=mysql`) dan sengaja
 * tidak ikut berpindah. Kalau ia ikut, ia harus dibaca sebelum kita tahu
 * partner mana yang dituju — padahal justru sesi itu yang menyimpan jawabannya.
 */
class SesiPartner
{
    public const KUNCI = 'partner_id';

    /** Dipanggil sekali, saat login berhasil. */
    public static function ingat(?Tenant $partner): void
    {
        if ($partner === null) {
            session()->forget(self::KUNCI);

            return;
        }

        session()->put(self::KUNCI, $partner->getTenantKey());
    }

    /**
     * Partner yang tercatat di sesi, kalau masih layak dipakai.
     *
     * Barisnya dibaca ulang tiap permintaan, tidak disimpan di sesi: hak akses
     * dan masa pakainya bisa berubah kapan saja di panel admin, dan yang
     * tersimpan di sesi akan tetap memakai keadaan saat login.
     */
    public static function partner(): ?Tenant
    {
        $id = session(self::KUNCI);

        if (blank($id)) {
            return null;
        }

        $partner = Tenant::find($id);

        // Partnernya sudah dihapus: sesinya tidak menunjuk apa pun lagi.
        if ($partner === null) {
            session()->forget(self::KUNCI);
        }

        return $partner;
    }

    public static function lupakan(): void
    {
        session()->forget(self::KUNCI);
    }
}
