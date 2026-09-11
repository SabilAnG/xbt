<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

/*
|--------------------------------------------------------------------------
| Tenant Routes
|--------------------------------------------------------------------------
|
| Here you can register the tenant routes for your application.
| These routes are loaded by the TenantRouteServiceProvider.
|
| Feel free to customize them however you want. Good luck!
|
*/

// Sengaja belum ada route di sini.
//
// Route contoh bawaan tenancy mendaftarkan '/' dengan
// PreventAccessFromCentralDomains, dan itu membalas 404 di domain pusat —
// halaman depan Hypersonic ikut mati karenanya.
//
// Halaman toko partner menyusul bersama pekerjaan landing page; saat itu
// route-nya ditulis di sini, di dalam grup middleware tenancy.
