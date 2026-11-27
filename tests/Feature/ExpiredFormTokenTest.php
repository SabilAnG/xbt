<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Formulir yang dibiarkan terbuka sampai tokennya kedaluwarsa tidak berakhir di "419 Page Expired". */
class ExpiredFormTokenTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        // Saat test, pemeriksaan CSRF biasanya dilewati; nyalakan agar token basi benar-benar ditolak.
        $this->app->instance(ValidateCsrfToken::class, new class($this->app, $this->app['encrypter']) extends ValidateCsrfToken
        {
            protected function runningUnitTests(): bool
            {
                return false;
            }
        });
    }

    public function test_expired_login_form_returns_to_login_with_message(): void
    {
        $this->from(route('login'))
            ->post(route('login.attempt'), ['_token' => 'basi', 'email' => 'admin@xbt.test', 'password' => 'rahasia123'])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email')
            ->assertSessionHasInput('email', 'admin@xbt.test')
            ->assertSessionMissing('_old_input.password');

        $this->assertGuest();
    }

    public function test_expired_logout_still_signs_out(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'owner']));

        $this->post(route('admin.logout'), ['_token' => 'basi'])->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_expired_admin_form_goes_back_with_input(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'owner']));

        $this->from(route('admin.units.index'))
            ->post(route('admin.units.store'), ['_token' => 'basi', 'name' => 'Lusin'])
            ->assertRedirect(route('admin.units.index'))
            ->assertSessionHas('error')
            ->assertSessionHasInput('name', 'Lusin');
    }
}
