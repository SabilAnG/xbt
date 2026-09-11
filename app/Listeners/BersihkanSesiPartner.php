<?php

namespace App\Listeners;

use App\Services\SesiPartner;
use Illuminate\Auth\Events\Logout;

/**
 * Melupakan partner begitu penggunanya keluar.
 *
 * Tanpa ini sesi yang sama masih menyimpan tandanya sesudah logout, dan orang
 * berikutnya yang masuk lewat browser itu — termasuk admin Hypersonic — sempat
 * mendarat di database partner sebelum tandanya tertimpa.
 */
class BersihkanSesiPartner
{
    public function handle(Logout $event): void
    {
        SesiPartner::lupakan();

        if (tenancy()->initialized) {
            tenancy()->end();
        }
    }
}
