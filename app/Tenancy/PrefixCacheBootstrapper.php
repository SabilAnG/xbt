<?php

namespace App\Tenancy;

use Illuminate\Cache\CacheManager;
use Stancl\Tenancy\Contracts\TenancyBootstrapper;
use Stancl\Tenancy\Contracts\Tenant;

/**
 * Memisahkan cache antar partner lewat prefix, bukan tag.
 *
 * Bawaan tenancy memakai cache tag, dan tag tidak didukung cache store yang
 * dipakai aplikasi ini (database). Tanpa penggantinya, tiap halaman yang
 * menyentuh cache — termasuk halaman depan — gagal dengan "This cache store
 * does not support tagging".
 *
 * Prefix melakukan pekerjaan yang sama untuk keperluan kami: kunci milik satu
 * partner tidak pernah terbaca partner lain. Yang tidak didapat adalah
 * menghapus seluruh cache satu partner sekaligus, dan itu memang tidak pernah
 * kami lakukan.
 */
class PrefixCacheBootstrapper implements TenancyBootstrapper
{
    private ?string $prefixAsli = null;

    public function bootstrap(Tenant $tenant): void
    {
        $this->prefixAsli ??= config('cache.prefix');

        $this->pakaiPrefix($this->prefixAsli.'_partner_'.$tenant->getTenantKey());
    }

    public function revert(): void
    {
        if ($this->prefixAsli === null) {
            return;
        }

        $this->pakaiPrefix($this->prefixAsli);
    }

    /**
     * Prefix dibaca saat store dibangun, jadi store yang sudah terlanjur dibuat
     * harus dibuang — kalau tidak, yang berubah cuma confignya dan cache tetap
     * memakai prefix lama.
     */
    private function pakaiPrefix(?string $prefix): void
    {
        config(['cache.prefix' => $prefix]);

        app()->forgetInstance('cache');
        app()->forgetInstance('cache.store');
        app()->singleton('cache', fn ($app) => new CacheManager($app));
        app()->bind('cache.store', fn ($app) => $app['cache']->driver());
    }
}
