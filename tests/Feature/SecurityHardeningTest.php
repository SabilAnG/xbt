<?php

namespace Tests\Feature;

use App\Models\ContentBlock;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use App\Models\Wallet;
use App\Services\InventoryService;
use App\Support\SafeHtml;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Tests\TestCase;

/** Temuan audit keamanan: XSS tersimpan, unggahan SVG, tebak password, tracking, pembayaran ganda. */
class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_rich_text_keeps_formatting_but_drops_scripts(): void
    {
        $clean = SafeHtml::clean(
            '<p style="color: red; background-image: url(x)" onclick="steal()"><strong>Knalpot</strong> racing</p>'
            .'<script>alert(1)</script><img src=x onerror=alert(2)><svg onload=alert(3)></svg>'
            .'<a href="java&#x09;script:alert(4)">a</a><a href="https://hypersonic.id">b</a><iframe src="//evil"></iframe>'
        );

        $this->assertStringContainsString('<strong>Knalpot</strong> racing', $clean);
        $this->assertStringContainsString('style="color: red"', $clean);
        $this->assertStringContainsString('href="https://hypersonic.id"', $clean);
        foreach (['<script', 'alert(1)', 'onerror', 'onclick', 'onload', '<img', '<svg', '<iframe', 'javascript', 'url('] as $bad) {
            $this->assertStringNotContainsString($bad, $clean);
        }
        $this->assertSame('Tom &amp; Jerry', SafeHtml::clean('Tom & Jerry'));
    }

    public function test_public_pages_do_not_run_scripts_stored_by_admins(): void
    {
        Product::create([
            'name' => 'Knalpot Beat', 'slug' => 'knalpot-beat', 'price' => 450_000, 'currency' => 'IDR', 'is_active' => true,
            'description' => '<p>Suara <b>ngebass</b></p><script>fetch("/admin/pengguna")</script><img src=x onerror=alert(1)>',
        ]);
        ContentBlock::create(['key' => 'home.text.17', 'page' => 'home', 'label' => 'Judul', 'type' => 'text', 'value' => 'Before <img src=x onerror=alert(1)>']);

        $this->get(route('products.show', 'knalpot-beat'))->assertOk()
            ->assertSee('<b>ngebass</b>', false)->assertDontSee('fetch("/admin/pengguna")', false)->assertDontSee('onerror', false);
        $this->get(route('home'))->assertOk()->assertDontSee('onerror=alert(1)', false);
    }

    public function test_pages_send_security_headers(): void
    {
        $policy = $this->get(route('home'))->assertOk()
            ->assertHeader('Permissions-Policy')
            ->assertHeaderMissing('Strict-Transport-Security')
            ->headers->get('Content-Security-Policy');

        foreach (["default-src 'self'", "object-src 'none'", "frame-ancestors 'self'", "base-uri 'self'", 'https://fonts.bunny.net'] as $directive) {
            $this->assertStringContainsString($directive, $policy);
        }

        // HSTS hanya lewat https (di produksi, di belakang nginx).
        $this->get('https://localhost/admin/login')->assertHeader('Strict-Transport-Security', 'max-age=31536000');
    }

    public function test_site_settings_reject_svg_uploads(): void
    {
        Storage::fake('site');
        $this->actingAs(User::factory()->create(['role' => 'owner']));

        $svg = UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"/>');

        $this->put(route('admin.site-settings.update'), ['site_name' => 'Hypersonic', 'site_logo' => $svg])->assertSessionHasErrors('site_logo');
    }

    public function test_login_locks_an_account_after_repeated_wrong_passwords(): void
    {
        User::factory()->create(['email' => 'pemilik@xbt.test', 'password' => 'rahasia-benar', 'role' => 'owner']);

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('login.attempt'), ['email' => 'pemilik@xbt.test', 'password' => 'salah-'.$i])->assertSessionHasErrors('email');
        }

        // Password benar pun ditahan sampai jeda habis.
        $this->post(route('login.attempt'), ['email' => 'pemilik@xbt.test', 'password' => 'rahasia-benar'])
            ->assertSessionHasErrors(['email' => 'Terlalu banyak percobaan masuk. Coba lagi dalam 15 menit.']);
        $this->assertGuest();

        // Tebakan dari banyak IP tetap kena batas per akun.
        for ($i = 0; $i < 20; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.'.($i + 1)])
                ->post(route('login.attempt'), ['email' => 'pemilik@xbt.test', 'password' => 'salah'])->assertSessionHasErrors('email');
        }
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.9.9'])
            ->post(route('login.attempt'), ['email' => 'pemilik@xbt.test', 'password' => 'rahasia-benar'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_order_tracking_is_rate_limited(): void
    {
        for ($i = 0; $i < 20; $i++) {
            $this->postJson(route('tracking.track'), ['order_number' => 'JL-202612-'.str_pad((string) $i, 4, '0', STR_PAD_LEFT)])->assertNotFound();
        }

        $this->postJson(route('tracking.track'), ['order_number' => 'JL-202612-0099'])->assertTooManyRequests();
    }

    public function test_sale_payment_rechecks_receivable_against_the_locked_row(): void
    {
        $wallet = Wallet::create(['name' => 'Kas', 'type' => 'cash', 'opening_balance' => 0, 'is_active' => true]);
        $sale = Sale::create(['invoice_number' => 'JL-202612-0001', 'sold_at' => '2026-12-01', 'total' => 100_000, 'paid_amount' => 0, 'status' => 'posted']);
        $staleCopy = Sale::findOrFail($sale->id);
        $service = app(InventoryService::class);

        $service->paySale($sale, 100_000, $wallet->id, '2026-12-01');

        // Kiriman kedua (klik ganda) masih memegang data lama "belum dibayar".
        $this->expectException(InvalidArgumentException::class);
        try {
            $service->paySale($staleCopy, 100_000, $wallet->id, '2026-12-01');
        } finally {
            $this->assertEquals(100_000, (float) $sale->fresh()->paid_amount);
        }
    }
}
