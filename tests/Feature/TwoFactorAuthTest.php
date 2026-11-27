<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Totp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Verifikasi 2 langkah: pasang, login dengan kode / kode cadangan, reset oleh pemilik, ganti password. */
class TwoFactorAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_totp_matches_the_rfc_6238_reference_vector(): void
    {
        // RFC 6238 lampiran B, rahasia "12345678901234567890" (base32 di bawah), T = 59 detik.
        $secret = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';

        $this->assertSame('287082', Totp::code($secret, 59));
        $this->assertNotNull(Totp::match($secret, '287 082', 59));
        $this->assertNull(Totp::match($secret, '287083', 59));
        $this->assertSame(32, strlen(Totp::generateSecret()));
    }

    public function test_owner_enables_two_factor_and_then_needs_a_code_to_sign_in(): void
    {
        $owner = User::factory()->create(['email' => 'pemilik@xbt.test', 'password' => 'rahasia123', 'role' => 'owner']);
        $this->actingAs($owner);

        $this->get(route('admin.dashboard'))->assertSee('belum memakai verifikasi 2 langkah');
        $this->post(route('admin.account.two-factor.start'))->assertRedirect();
        $secret = session('two_factor.setup_secret');
        $this->get(route('admin.account.security'))->assertOk()->assertSee(trim(chunk_split($secret, 4, ' ')))
            ->assertSee('otpauth://totp/IHC%20Racing%3Apemilik%40xbt.test?secret='.$secret, false);
        $this->get(route('login.two-factor'))->assertRedirect(); // sudah masuk → bukan halaman verifikasi

        $this->post(route('admin.account.two-factor.confirm'), ['code' => '000000'])->assertSessionHasErrors('code');
        $this->post(route('admin.account.two-factor.confirm'), ['code' => Totp::code($secret)])->assertSessionHas('recovery_codes');
        $owner->refresh();
        $this->assertTrue($owner->hasTwoFactor());
        $this->assertCount(8, $owner->two_factor_recovery_codes);
        $this->assertNotSame($secret, $owner->getRawOriginal('two_factor_secret'), 'Rahasia tersimpan terenkripsi');

        // Login ulang: password benar belum cukup.
        $this->post(route('admin.logout'));
        $this->post(route('login.attempt'), ['email' => 'pemilik@xbt.test', 'password' => 'rahasia123'])->assertRedirect(route('login.two-factor'));
        $this->assertGuest();
        $this->get(route('login.two-factor'))->assertOk()->assertSee('Masukkan kode')->assertSee('HP hilang? Pakai kode cadangan');
        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));

        $this->post(route('login.two-factor.verify'), ['code' => '123456'])->assertSessionHasErrors('code');
        $this->assertGuest();

        $code = Totp::code($owner->two_factor_secret);
        $this->post(route('login.two-factor.verify'), ['code' => $code])->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($owner);
        $this->get(route('admin.dashboard'))->assertOk()->assertDontSee('belum memakai verifikasi 2 langkah');

        // Kode yang sama tidak bisa dipakai dua kali.
        $this->post(route('admin.logout'));
        $this->post(route('login.attempt'), ['email' => 'pemilik@xbt.test', 'password' => 'rahasia123']);
        $this->post(route('login.two-factor.verify'), ['code' => $code])->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    public function test_recovery_code_works_once_and_owner_can_reset_a_lost_phone(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $staff = User::factory()->create(['email' => 'gudang@xbt.test', 'password' => 'rahasia123', 'role' => 'gudang']);
        $staff->forceFill(['two_factor_secret' => Totp::generateSecret(), 'two_factor_recovery_codes' => ['AAAAA-BBBBB', 'CCCCC-DDDDD'], 'two_factor_confirmed_at' => now()])->save();

        $this->post(route('login.attempt'), ['email' => 'gudang@xbt.test', 'password' => 'rahasia123'])->assertRedirect(route('login.two-factor'));
        $this->post(route('login.two-factor.verify'), ['recovery_code' => 'aaaaa-bbbbb'])->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($staff);
        $this->assertSame(['CCCCC-DDDDD'], $staff->fresh()->two_factor_recovery_codes);

        $this->post(route('admin.logout'));
        $this->post(route('login.attempt'), ['email' => 'gudang@xbt.test', 'password' => 'rahasia123']);
        $this->post(route('login.two-factor.verify'), ['recovery_code' => 'AAAAA-BBBBB'])->assertSessionHasErrors('recovery_code');
        $this->assertGuest();

        // Staf tidak bisa mereset 2 langkah orang lain; pemilik bisa.
        $this->actingAs($staff);
        $this->post(route('admin.users.two-factor.reset', $owner))->assertForbidden();
        $this->actingAs($owner);
        $this->get(route('admin.users.index'))->assertSee('2 langkah aktif');
        $this->post(route('admin.users.two-factor.reset', $staff))->assertSessionHas('status');
        $this->assertFalse($staff->fresh()->hasTwoFactor());
    }

    public function test_too_many_wrong_codes_send_the_user_back_to_the_password_step(): void
    {
        $user = User::factory()->create(['email' => 'kasir@xbt.test', 'password' => 'rahasia123', 'role' => 'kasir']);
        $user->forceFill(['two_factor_secret' => Totp::generateSecret(), 'two_factor_recovery_codes' => [], 'two_factor_confirmed_at' => now()])->save();

        $this->post(route('login.attempt'), ['email' => 'kasir@xbt.test', 'password' => 'rahasia123']);
        for ($i = 0; $i < 5; $i++) {
            $this->post(route('login.two-factor.verify'), ['code' => '00000'.$i]);
        }

        $this->post(route('login.two-factor.verify'), ['code' => Totp::code($user->two_factor_secret)])
            ->assertRedirect(route('login'))->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_changing_password_requires_the_current_one_and_signs_out_other_devices(): void
    {
        $user = User::factory()->create(['password' => 'rahasia123', 'role' => 'gudang']);
        $this->actingAs($user);

        $this->get(route('admin.account.security'))->assertOk()->assertSee('Ganti password');
        $this->put(route('admin.account.password'), ['current_password' => 'salah', 'password' => 'baru12345', 'password_confirmation' => 'baru12345'])
            ->assertSessionHasErrors('current_password');
        $this->put(route('admin.account.password'), ['current_password' => 'rahasia123', 'password' => 'pendek', 'password_confirmation' => 'pendek'])
            ->assertSessionHasErrors('password');

        $this->put(route('admin.account.password'), ['current_password' => 'rahasia123', 'password' => 'baru12345', 'password_confirmation' => 'baru12345'])
            ->assertSessionHasNoErrors();
        $this->assertTrue(password_verify('baru12345', $user->fresh()->password));

        // Sesi lain yang masih memegang sidik password lama otomatis keluar.
        $this->withSession(['password_hash_web' => 'sidik-lama'])->get(route('admin.dashboard'))->assertRedirect(route('login'));
    }

    public function test_security_check_flags_default_passwords_and_missing_two_factor(): void
    {
        User::factory()->create(['email' => 'admin@hypersonic.id', 'password' => 'password', 'role' => 'owner']);

        $this->artisan('security:check')
            ->expectsOutputToContain('admin@hypersonic.id memakai password lemah')
            ->expectsOutputToContain('belum mengaktifkan verifikasi 2 langkah')
            ->assertExitCode(1);
    }
}
