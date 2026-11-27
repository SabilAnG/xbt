<?php

namespace Tests;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Ganti pengguna = sesi baru, seperti login sungguhan. Tanpa ini
     * AuthenticateSession melihat sidik password pengguna sebelumnya di sesi
     * dan (dengan benar) mengeluarkan pengguna yang baru.
     *
     * @param  string|null  $guard
     */
    public function actingAs(Authenticatable $user, $guard = null): static
    {
        $this->flushSession();

        return parent::actingAs($user, $guard);
    }
}
