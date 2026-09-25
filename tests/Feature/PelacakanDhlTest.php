<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Shipment;
use App\Services\PelacakanDhl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TransportDhlPalsu;
use Tests\TestCase;

/**
 * Pelacakan DHL di halaman /tracking.
 *
 * Yang diuji di sini bukan kebenaran data DHL — itu urusan mereka — melainkan
 * tiga janji yang dipegang halaman publik: status kurir benar-benar tersimpan,
 * kuota API tidak dihambur, dan DHL yang sedang mati tidak ikut mematikan
 * halaman orang yang menunggu paketnya.
 */
class PelacakanDhlTest extends TestCase
{
    use RefreshDatabase;

    private const KREDENSIAL = [
        'username' => 'uji',
        'password' => 'rahasia',
        'environment' => 'test',
        'menit_segar' => 15,
    ];

    // ------------------------------------------------------------ menyimpan

    public function test_status_dan_peristiwa_dari_dhl_tersimpan(): void
    {
        $kiriman = $this->kiriman();
        $transport = TransportDhlPalsu::terkirim('1234567890');

        $this->layanan($transport)->segarkan($kiriman);

        $kiriman->refresh();

        $this->assertSame('Delivered', $kiriman->status);
        $this->assertSame('Delivered - Signed for by: BUDI', $kiriman->status_description);
        $this->assertNotNull($kiriman->last_checked);
        $this->assertCount(2, $kiriman->events);

        // Linimasa halaman membaca index 0 sebagai langkah aktif, jadi urutan
        // terbaru-dulu itu bagian dari janjinya, bukan kebetulan.
        $this->assertSame('Delivered - Signed for by: BUDI', $kiriman->events[0]->description);
        $this->assertSame('Jakarta - Indonesia', $kiriman->events[0]->location);

        // Waktu peristiwa disimpan dengan offsetnya diperhitungkan, bukan
        // dibaca mentah sebagai waktu lokal server.
        $this->assertSame(
            '2026-09-19 03:05:00',
            $kiriman->status_updated_at->utc()->format('Y-m-d H:i:s'),
        );
    }

    /**
     * DHL mengirim SELURUH riwayat tiap kali ditanya. Tanpa pencocokan, satu
     * kiriman yang dilacak lima kali akan punya lima salinan tiap peristiwa.
     */
    public function test_peristiwa_yang_sama_tidak_digandakan(): void
    {
        $kiriman = $this->kiriman();
        $transport = TransportDhlPalsu::terkirim('1234567890');
        $layanan = $this->layanan($transport, ['menit_segar' => 0]);

        $layanan->segarkan($kiriman);
        $layanan->segarkan($kiriman->refresh());

        $this->assertSame(2, $transport->panggilan, 'Cache dimatikan, jadi DHL memang ditanya dua kali.');
        $this->assertCount(2, $kiriman->refresh()->events);
    }

    // --------------------------------------------------------------- cache

    public function test_cache_menahan_panggilan_kedua(): void
    {
        $kiriman = $this->kiriman();
        $transport = TransportDhlPalsu::terkirim('1234567890');
        $layanan = $this->layanan($transport);

        $this->assertTrue($layanan->segarkan($kiriman));
        $this->assertFalse($layanan->segarkan($kiriman->refresh()), 'Panggilan kedua seharusnya ditahan cache.');

        $this->assertSame(1, $transport->panggilan);
    }

    // ------------------------------------------------------------ kegagalan

    /**
     * Orang yang menunggu paket lebih baik melihat status kemarin daripada
     * layar error.
     */
    public function test_dhl_yang_mati_tidak_menghapus_status_lama(): void
    {
        $kiriman = $this->kiriman([
            'status' => 'In transit',
            'status_description' => 'Departed facility',
        ]);

        $transport = TransportDhlPalsu::rusak();
        $this->layanan($transport)->segarkan($kiriman);

        $kiriman->refresh();

        $this->assertSame('In transit', $kiriman->status);
        $this->assertSame('Departed facility', $kiriman->status_description);

        // Tetap ditandai sudah ditanya: DHL yang sedang bermasalah tidak boleh
        // dihujani ulang oleh setiap pengunjung halaman.
        $this->assertNotNull($kiriman->last_checked);
    }

    public function test_halaman_tracking_tetap_menjawab_saat_dhl_mati(): void
    {
        $kiriman = $this->kiriman(['status' => 'In transit']);
        $this->app->instance(PelacakanDhl::class, $this->layanan(TransportDhlPalsu::rusak()));

        $this->postJson(route('tracking.track'), ['order_number' => 'HS-0001'])
            ->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.order_ref_number', 'HS-0001')
            ->assertJsonPath('data.shipments.0.status', 'In transit');
    }

    // ------------------------------------------------------- tidak menyentuh

    public function test_kiriman_bukan_dhl_tidak_ditanyakan(): void
    {
        $kiriman = $this->kiriman(['provider' => 'JNE']);
        $transport = TransportDhlPalsu::terkirim();

        $this->assertFalse($this->layanan($transport)->segarkan($kiriman));
        $this->assertSame(0, $transport->panggilan);
    }

    /** Keadaan bawaan di mesin pengembangan: kredensial kosong. */
    public function test_tanpa_kredensial_fitur_diam(): void
    {
        $kiriman = $this->kiriman();
        $transport = TransportDhlPalsu::terkirim();
        $layanan = new PelacakanDhl(['username' => '', 'password' => ''], $transport);

        $this->assertFalse($layanan->aktif());
        $this->assertFalse($layanan->segarkan($kiriman));
        $this->assertSame(0, $transport->panggilan);
        $this->assertNull($kiriman->refresh()->last_checked);
    }

    // --------------------------------------------------------------- bantu

    private function layanan(TransportDhlPalsu $transport, array $ubah = []): PelacakanDhl
    {
        return new PelacakanDhl(array_merge(self::KREDENSIAL, $ubah), $transport);
    }

    private function kiriman(array $atribut = []): Shipment
    {
        $order = Order::create([
            'order_number' => 'HS-0001',
            'customer_name' => 'Budi',
            'status' => 'shipped',
        ]);

        return $order->shipments()->create(array_merge([
            'provider' => 'DHL Express',
            'tracking_number' => '1234567890',
            'sort_order' => 1,
        ], $atribut));
    }
}
