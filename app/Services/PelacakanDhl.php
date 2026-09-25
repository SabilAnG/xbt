<?php

namespace App\Services;

use App\Models\Shipment;
use Ihc\DhlGate\Client;
use Ihc\DhlGate\Contracts\Transport;
use Ihc\DhlGate\Exception\Exception as DhlException;
use Ihc\DhlGate\Exception\NotFoundException;
use Ihc\DhlGate\Results\TrackedShipment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Menyegarkan status kiriman DHL untuk halaman /tracking.
 *
 * Tabel `shipments` memang sudah dirancang untuk ini: `status`,
 * `status_description`, `status_updated_at` menyimpan jawaban kurir, dan
 * `last_checked` menyimpan kapan terakhir ditanya. Jadi yang ditambahkan di
 * sini hanya pengisinya — bentuk respons yang dibaca JavaScript halaman tidak
 * berubah sama sekali.
 *
 * Dua sikap yang dipegang:
 *
 * - Halaman publik TIDAK BOLEH ikut mati saat DHL bermasalah. Setiap kegagalan
 *   ditelan, dicatat ke log, dan yang tersimpan tetap disajikan. Orang yang
 *   melacak paket lebih baik melihat status kemarin daripada layar error.
 * - Fitur ini mati sendiri bila kredensialnya kosong. Itu keadaan bawaan di
 *   mesin pengembangan, dan halaman harus tetap bekerja di sana.
 */
class PelacakanDhl
{
    private ?Client $client = null;

    /**
     * @param  array<string, mixed>  $konfigurasi  isi config('services.dhl')
     * @param  Transport|null  $transport  disuntik oleh tes; produksi memakai cURL bawaan paket
     */
    public function __construct(
        private readonly array $konfigurasi,
        private readonly ?Transport $transport = null,
    ) {}

    /** Kredensial terisi atau tidak. Tanpa keduanya fitur ini diam. */
    public function aktif(): bool
    {
        return filled($this->konfigurasi['username'] ?? null)
            && filled($this->konfigurasi['password'] ?? null);
    }

    /**
     * Segarkan setiap kiriman DHL dalam daftar.
     *
     * @param  iterable<int, Shipment>  $kiriman
     */
    public function segarkanSemua(iterable $kiriman): void
    {
        foreach ($kiriman as $satu) {
            $this->segarkan($satu);
        }
    }

    /**
     * Tanya DHL tentang satu kiriman, lalu simpan jawabannya.
     *
     * @return bool benar bila DHL benar-benar dihubungi — bukan bila datanya berubah
     */
    public function segarkan(Shipment $kiriman): bool
    {
        if (! $this->aktif() || ! $kiriman->pakaiDhl() || blank($kiriman->tracking_number)) {
            return false;
        }

        if ($this->masihSegar($kiriman)) {
            return false;
        }

        try {
            $terlacak = $this->client()->tracking()->find($kiriman->tracking_number);
        } catch (NotFoundException) {
            // Nomor resi yang belum dikenal DHL bukan kesalahan: label yang baru
            // dibuat butuh waktu sampai muncul di sistem mereka.
            $this->tandaiSudahDitanya($kiriman);

            return true;
        } catch (DhlException|\Throwable $e) {
            Log::warning('Pelacakan DHL gagal.', [
                'tracking_number' => $kiriman->tracking_number,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            // `last_checked` tetap dimajukan walau gagal. Kalau tidak, DHL yang
            // sedang bermasalah akan dihujani ulang oleh setiap pengunjung.
            $this->tandaiSudahDitanya($kiriman);

            return true;
        }

        if ($terlacak === null) {
            $this->tandaiSudahDitanya($kiriman);

            return true;
        }

        $this->simpan($kiriman, $terlacak);

        return true;
    }

    // ------------------------------------------------------------- internal

    private function masihSegar(Shipment $kiriman): bool
    {
        $menit = (int) ($this->konfigurasi['menit_segar'] ?? 15);

        return $menit > 0
            && $kiriman->last_checked !== null
            && $kiriman->last_checked->greaterThan(now()->subMinutes($menit));
    }

    private function tandaiSudahDitanya(Shipment $kiriman): void
    {
        $kiriman->forceFill(['last_checked' => now()])->save();
    }

    private function simpan(Shipment $kiriman, TrackedShipment $terlacak): void
    {
        foreach ($terlacak->events as $peristiwa) {
            $terjadi = $this->keUtc($peristiwa->occurredAt());

            // Peristiwa tanpa tanggal tidak bisa ditempatkan di linimasa, dan
            // linimasa halaman diurutkan justru dari kolom itu.
            if ($terjadi === null) {
                continue;
            }

            // Dicocokkan, bukan ditimpa: satu nomor resi ditanyakan berkali-kali
            // dan jawabannya memuat seluruh riwayat tiap kali. Menghapus lalu
            // menulis ulang juga akan menghanguskan catatan yang diketik admin
            // sendiri di menu Orders.
            $kiriman->events()->firstOrCreate(
                [
                    'happened_at' => $terjadi,
                    'description' => $peristiwa->description,
                ],
                [
                    'status' => $peristiwa->typeCode,
                    'location' => $peristiwa->location,
                ],
            );
        }

        $terbaru = $terlacak->latestEvent();

        $kiriman->forceFill([
            'status' => $terlacak->status,
            'status_description' => $terlacak->description ?? $terbaru?->description,
            'status_updated_at' => $this->keUtc($terbaru?->occurredAt()) ?? $kiriman->status_updated_at,
            'last_checked' => now(),
        ])->save();
    }

    /**
     * Waktu DHL -> UTC, satuan waktu yang dipakai aplikasi ini.
     *
     * DHL menyebut tiap checkpoint dalam waktu setempat berikut offsetnya —
     * "10:05 +07:00" di Jakarta, "23:40 -05:00" di Cincinnati. Eloquent
     * menuliskan objek tanggal apa adanya tanpa mengubah zona, jadi tanpa
     * langkah ini yang tersimpan adalah angka jam dindingnya saja dan offsetnya
     * hangus. Akibatnya bukan sekadar salah tampil: satu kiriman yang melewati
     * beberapa negara akan punya linimasa yang urutannya kacau, dan linimasa
     * halaman justru diurutkan dari kolom itu.
     */
    private function keUtc(?\DateTimeInterface $waktu): ?Carbon
    {
        return $waktu === null ? null : Carbon::instance($waktu)->utc();
    }

    private function client(): Client
    {
        return $this->client ??= Client::make($this->konfigurasi, $this->transport);
    }
}
