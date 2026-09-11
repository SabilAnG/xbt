<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\Tenant;

/**
 * Mengisi identitas toko partner: nama, email, dan nomor WhatsApp-nya sendiri.
 *
 * Tanpa ini tabel settings partner kosong, dan seluruh halaman tokonya jatuh ke
 * nilai bawaan yang tertulis di view — yakni nama, email, dan nomor WhatsApp
 * Hypersonic. Akibatnya bukan sekadar salah nama: pembeli yang menekan tombol
 * WhatsApp di toko partner akan menghubungi Hypersonic, bukan partner yang
 * menjual barangnya.
 *
 * Yang diisi hanya yang memang sudah kita ketahui dari pendaftarannya. Sisanya
 * — alamat bengkel, media sosial, teks halaman — dibiarkan kosong supaya
 * partner mengisinya sendiri lewat menu Site settings, dan tidak ada nilai
 * palsu yang terlanjur terlihat seperti benar.
 */
class IdentitasTokoPartner
{
    /**
     * Dijalankan di dalam `$tenant->run()`, jadi seluruh penulisan mendarat di
     * database partner.
     *
     * Isian yang sudah punya nilai tidak disentuh. Saat partner baru dibuat
     * tabelnya memang kosong, jadi ini tidak mengubah apa pun di sana — tapi
     * ia membuat metode yang sama aman dipakai untuk menambal partner lama,
     * tanpa menimpa nama atau nomor yang sudah mereka ganti sendiri.
     *
     * @return list<string> kunci yang baru terisi
     */
    public function isi(Tenant $partner): array
    {
        $terisi = [];

        foreach ($this->nilai($partner) as $kunci => $nilai) {
            if (blank($nilai) || filled(Setting::get($kunci))) {
                continue;
            }

            Setting::put($kunci, $nilai);
            $terisi[] = $kunci;
        }

        return $terisi;
    }

    /** @return array<string, string|null> */
    private function nilai(Tenant $partner): array
    {
        return [
            'site.name' => $partner->name,
            'site.tagline' => $partner->name,

            'contact.email' => $partner->owner_email,
            'contact.phone' => $partner->owner_phone,

            // Nomor pemilik jadi tujuan seluruh tombol WhatsApp di tokonya.
            // Keempatnya diisi sama: tiga nomor sales adalah pembagian milik
            // Hypersonic, dan partner baru hampir pasti hanya punya satu nomor.
            'whatsapp.primary' => $this->nomorWa($partner->owner_phone),
            'whatsapp.sales1' => $this->nomorWa($partner->owner_phone),
            'whatsapp.sales2' => $this->nomorWa($partner->owner_phone),
            'whatsapp.sales3' => $this->nomorWa($partner->owner_phone),
        ];
    }

    /**
     * "08123456789" jadi "628123456789" — bentuk yang dipakai tautan wa.me.
     *
     * Nomor yang sudah berawalan 62 atau +62 dibiarkan; yang kosong tetap
     * kosong, supaya tombolnya tidak mengarah ke nomor yang tidak ada.
     */
    private function nomorWa(?string $nomor): ?string
    {
        $angka = preg_replace('/\D/', '', (string) $nomor);

        if (blank($angka)) {
            return null;
        }

        if (str_starts_with($angka, '0')) {
            return '62'.ltrim($angka, '0');
        }

        return $angka;
    }
}
