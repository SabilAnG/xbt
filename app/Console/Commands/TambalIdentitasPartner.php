<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\IdentitasTokoPartner;
use Illuminate\Console\Command;

/**
 * Mengisikan identitas toko ke partner yang sudah telanjur dibuat.
 *
 * Identitas diisi saat partner disetujui. Partner yang disetujui sebelum
 * langkah itu ada tokonya masih memakai nama, email, dan nomor WhatsApp
 * Hypersonic — dan pembeli yang menekan tombol WhatsApp di sana menghubungi
 * kami, bukan yang berjualan. Perintah ini menambal mereka.
 *
 * Aman diulang: isian yang sudah bernilai — termasuk yang sudah diganti sendiri
 * oleh partner lewat menu Site settings — tidak disentuh.
 */
class TambalIdentitasPartner extends Command
{
    protected $signature = 'partner:tambal-identitas {--slug= : Tambal satu partner saja}';

    protected $description = 'Isikan nama, email, dan WhatsApp partner ke toko yang masih memakai identitas Hypersonic';

    public function handle(IdentitasTokoPartner $identitas): int
    {
        $partner = Tenant::query()
            ->where('status', Tenant::AKTIF)
            ->when($this->option('slug'), fn ($q, $slug) => $q->where('slug', $slug))
            ->get();

        if ($partner->isEmpty()) {
            $this->warn('Tidak ada partner aktif yang cocok.');

            return self::SUCCESS;
        }

        foreach ($partner as $satu) {
            // Kegagalan satu partner tidak boleh menghentikan sisanya —
            // database yang sedang bermasalah justru yang paling sering perlu
            // dilewati, bukan yang menghentikan seluruh penambalan.
            try {
                $terisi = $satu->run(fn (): array => $identitas->isi($satu));
            } catch (\Throwable $e) {
                $this->error("{$satu->slug}: gagal — {$e->getMessage()}");

                continue;
            }

            $this->line($terisi === []
                ? "  {$satu->slug}: sudah punya identitas sendiri, dilewati"
                : "  {$satu->slug}: terisi ".count($terisi).' ('.implode(', ', $terisi).')');
        }

        return self::SUCCESS;
    }
}
